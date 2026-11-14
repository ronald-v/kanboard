<?php
namespace Kanboard\Plugin\RvSoftware\Model;

use DomainException;
use Otp\Otp;
use Base32\Base32;

class RvSecurityModel extends RvBaseModel
{
    public function key()
    {
        $key = base64_decode((string)(defined('PROJECTA_KEY') ? PROJECTA_KEY : getenv('PROJECTA_KEY')), true);
        if ($key === false || strlen($key) !== 32) {
            throw new DomainException('Configura PROJECTA_KEY con una clave aleatoria de 32 bytes en Base64.');
        }
        return $key;
    }

    public function seal($secret)
    {
        $nonce = random_bytes(12);
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'Projecta MFA');
        if ($cipher === false) { throw new DomainException('No fue posible proteger el factor.'); }
        return base64_encode($nonce.$tag.$cipher);
    }

    public function unseal($value)
    {
        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < 29) { throw new DomainException('Factor protegido inválido.'); }
        $plain = openssl_decrypt(substr($data,28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($data,0,12), substr($data,12,16), 'Projecta MFA');
        if ($plain === false) { throw new DomainException('No fue posible abrir el factor.'); }
        return $plain;
    }

    public function factor($userId)
    {
        $factor=$this->sql('SELECT * FROM rv_auth_factors WHERE user_id=?', [$userId])->fetch(\PDO::FETCH_ASSOC);
        if ($factor) { return $factor; }
        $user=$this->userModel->getById($userId);
        // Import an existing native factor without exposing or replacing it during setup.
        if ($user && $user['twofactor_activated'] && $user['twofactor_secret']) {
            $sealed=$this->seal($user['twofactor_secret']);
            $verb=DB_DRIVER==='mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $this->sql($verb.' INTO rv_auth_factors(user_id,secret) VALUES (?,?)',[$userId,$sealed]);
            $this->sql('UPDATE users SET twofactor_secret=? WHERE id=?',['',$userId]);
            return $this->sql('SELECT * FROM rv_auth_factors WHERE user_id=?',[$userId])->fetch(\PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function consume($kind, $identity, $limit, $seconds)
    {
        $bucket = hash('sha256',$kind.'|'.mb_strtolower(trim((string)$identity)));
        $now = time();
        $params = [$bucket,1,$now+$seconds,$now,$now,$now+$seconds];
        if (DB_DRIVER === 'mysql') {
            $sql = 'INSERT INTO rv_auth_limits(bucket,attempts,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=?,1,attempts+1), expires_at=IF(expires_at<=?,?,expires_at)';
        } else {
            $sql = 'INSERT INTO rv_auth_limits(bucket,attempts,expires_at) VALUES (?,?,?) ON CONFLICT(bucket) DO UPDATE SET attempts=CASE WHEN expires_at<=? THEN 1 ELSE attempts+1 END, expires_at=CASE WHEN expires_at<=? THEN ? ELSE expires_at END';
        }
        $this->sql($sql,$params);
        $attempts = (int)$this->sql('SELECT attempts FROM rv_auth_limits WHERE bucket=?',[$bucket])->fetchColumn();
        if ($attempts > $limit) { throw new DomainException('Demasiados intentos. Espera antes de volver a intentar.'); }
        // Bound retained limiter rows without a cron dependency.
        if (random_int(1,100) === 1) { $this->sql('DELETE FROM rv_auth_limits WHERE expires_at<?',[$now-86400]); }
        return $attempts;
    }

    public function clear($kind, $identity)
    {
        $this->sql('DELETE FROM rv_auth_limits WHERE bucket=?',[hash('sha256',$kind.'|'.mb_strtolower(trim((string)$identity)))]);
    }

    public function counter($secret, $code)
    {
        if (! is_string($code) || ! preg_match('/^[0-9]{6}$/D',$code)) { return false; }
        $otp = new Otp;
        $now = (int)floor(time()/30);
        foreach ([$now,$now-1,$now+1] as $counter) {
            if (hash_equals((string)$otp->totp(Base32::decode($secret),$counter),$code)) { return $counter; }
        }
        return false;
    }

    public function enroll($userId, $secret, $code)
    {
        $counter = $this->counter($secret,$code);
        if ($counter === false || $this->factor($userId)) { throw new DomainException('No fue posible activar el factor.'); }
        $pdo=$this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $this->sql('INSERT INTO rv_auth_factors(user_id,secret,last_counter) VALUES (?,?,?)',[$userId,$this->seal($secret),$counter]);
            $this->sql('UPDATE users SET twofactor_activated=1,twofactor_secret=? WHERE id=?',['',$userId]);
            $codes=$this->newRecoveryCodes($userId);
            $this->audit('mfa.enrolled',$userId);
            $pdo->commit();
            return $codes;
        } catch (\Throwable $error) { $pdo->rollBack(); throw $error; }
    }

    public function verify($userId, $code)
    {
        $factor=$this->factor($userId);
        if (! $factor) { return false; }
        $counter=$this->counter($this->unseal($factor['secret']),$code);
        if ($counter === false) { return $this->useRecoveryCode($userId,$code); }
        return $this->sql('UPDATE rv_auth_factors SET last_counter=? WHERE user_id=? AND last_counter<?',[$counter,$userId,$counter])->rowCount() === 1;
    }

    public function newRecoveryCodes($userId)
    {
        $pdo=$this->db->getConnection(); $own=!$pdo->inTransaction();
        if ($own) { $pdo->beginTransaction(); }
        try {
            if (DB_DRIVER==='mysql') { $this->sql('SELECT user_id FROM rv_auth_factors WHERE user_id=? FOR UPDATE',[$userId])->fetchColumn(); }
            $this->sql('DELETE FROM rv_recovery_codes WHERE user_id=?',[$userId]);
            $codes=[];
            for ($i=0;$i<10;$i++) {
                $code=bin2hex(random_bytes(16));
                $this->sql('INSERT INTO rv_recovery_codes(user_id,code_hash) VALUES (?,?)',[$userId,hash('sha256',$code)]);
                $codes[]=$code;
            }
            if ($own) { $pdo->commit(); }
            return $codes;
        } catch (\Throwable $error) { if ($own && $pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }

    private function useRecoveryCode($userId, $code)
    {
        if (! is_string($code) || ! preg_match('/^[a-f0-9]{32}$/D',$code)) { return false; }
        $used=$this->sql('UPDATE rv_recovery_codes SET used_at=? WHERE user_id=? AND code_hash=? AND used_at IS NULL',[time(),$userId,hash('sha256',$code)])->rowCount()===1;
        if ($used) { $this->audit('mfa.recovery_used',$userId); }
        return $used;
    }

    public function audit($event, $targetId)
    {
        $this->sql('INSERT INTO rv_auth_audit(event_id,actor_id,target_id,event,created_at) VALUES (?,?,?,?,?)',[bin2hex(random_bytes(16)),$this->userSession->getId(),$targetId,$event,time()]);
    }

    public function resetFactor($userId)
    {
        $pdo=$this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $this->sql('DELETE FROM rv_recovery_codes WHERE user_id=?',[$userId]);
            $this->sql('DELETE FROM rv_auth_factors WHERE user_id=?',[$userId]);
            $this->sql('UPDATE users SET twofactor_activated=0,twofactor_secret=?,api_access_token=? WHERE id=?',['','',$userId]);
            $this->rememberMeSessionModel->removeAll($userId);
            $this->audit('mfa.reset',$userId);
            $pdo->commit();
        } catch (\Throwable $error) { $pdo->rollBack(); throw $error; }
    }
}
