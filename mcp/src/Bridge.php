<?php
namespace Projecta\Mcp;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

final class Bridge
{
    public function __construct(private readonly \Pimple\Container $container) {}

    public function call(string $tool,array $arguments): CallToolResult
    {
        $definitions=Tools::definitions();
        if (! isset($definitions[$tool])) { return $this->error('UNKNOWN_TOOL','Unknown tool'); }
        $definition=$definitions[$tool];
        try {
            $params=$this->parameters($definition,$arguments);
            $api=$this->container['rvApiModel'];
            $api->authorizeMethod($definition['method']);
            $this->authorizeObject($definition,$params);
            if (in_array($definition['action'],['get','list'],true)) { return $this->result($api->invoke($definition['method'],$params)); }
            return $this->write($definition,$params,$arguments['operation_key'] ?? '');
        } catch (\JsonRPC\Exception\AccessDeniedException $error) { return $this->error('FORBIDDEN','The connected user cannot perform this action.'); }
        catch (\InvalidArgumentException $error) { return $this->error('INVALID_INPUT','Invalid tool arguments.'); }
        catch (\DomainException $error) {
            $history=str_starts_with($error->getMessage(),'HISTORY_CONFLICT:');
            return $this->error($history ? 'HISTORY_CONFLICT' : 'OPERATION_REJECTED',$history ? 'This object has protected history. Close or archive it instead.' : 'The operation was rejected.');
        } catch (\Throwable $error) { return $this->error('OPERATION_UNCERTAIN','Check the object state before retrying a write. The operation key will not be executed twice.'); }
    }

    private function parameters(array $definition,array $arguments): array
    {
        if (array_diff(array_keys($arguments),array_keys($definition['schema']['properties']))) { throw new \InvalidArgumentException; }
        foreach ($definition['schema']['required'] as $required) { if (! array_key_exists($required,$arguments)) { throw new \InvalidArgumentException; } }
        if ($definition['action']==='create' || $definition['action']==='update') {
            $fields=$arguments['fields'] ?? [];
            if (! is_array($fields) || ! $fields || array_diff(array_keys($fields),array_keys($definition['schema']['properties']['fields']['properties']))) { throw new \InvalidArgumentException; }
            $params=$fields;
        } else { $params=$arguments; }
        unset($params['operation_key'],$params['fields']);
        if ($definition['action']!=='list' && $definition['action']!=='create') {
            $id=$arguments[$definition['id']];
            if (! is_int($id) || $id<1) { throw new \InvalidArgumentException; }
            $native=$definition['action']==='update' && $definition['object']!=='projects' ? 'id' : $definition['id'];
            $params[$native]=$id;
            if ($definition['action']==='update' && $definition['object']==='subtasks') {
                \Kanboard\Api\Authorization\SubtaskAuthorization::getInstance($this->container)->check('SubtaskProcedure','updateSubtask',$id);
                $row=$this->container['subtaskModel']->getById($id);
                if (! $row) { throw new \InvalidArgumentException; }
                $params['task_id']=(int)$row['task_id'];
            }
        }
        return $params;
    }

    private function authorizeObject(array $definition,array $params): void
    {
        $class=['projects'=>'ProjectProcedure','tasks'=>'TaskProcedure','subtasks'=>'SubtaskProcedure'][$definition['object']];
        \Kanboard\Api\Authorization\UserAuthorization::getInstance($this->container)->check($class,$definition['method']);
        $id=$params['id'] ?? $params[$definition['id']] ?? null;
        if ($definition['action']==='create' || $definition['action']==='list') {
            if ($definition['object']==='tasks') { \Kanboard\Api\Authorization\ProjectAuthorization::getInstance($this->container)->check('TaskProcedure',$definition['action']==='create' ? 'createTask' : 'getAllTasks',$params['project_id'] ?? 0); }
            if ($definition['object']==='subtasks') { \Kanboard\Api\Authorization\TaskAuthorization::getInstance($this->container)->check('SubtaskProcedure',$definition['action']==='create' ? 'createSubtask' : 'getAllSubtasks',$params['task_id'] ?? 0); }
        } elseif ($definition['object']==='projects') { \Kanboard\Api\Authorization\ProjectAuthorization::getInstance($this->container)->check($class,$definition['method'],$id); }
        elseif ($definition['object']==='tasks') { \Kanboard\Api\Authorization\TaskAuthorization::getInstance($this->container)->check($class,$definition['method'],$id); }
        else { \Kanboard\Api\Authorization\SubtaskAuthorization::getInstance($this->container)->check($class,$definition['method'],$id); }
    }

    private function write(array $definition,array $params,string $operationKey): CallToolResult
    {
        if (! preg_match('/^[A-Za-z0-9_.:-]{8,64}$/D',$operationKey)) { throw new \InvalidArgumentException; }
        $model=$this->container['rvSecurityModel']; $user=$this->container['userSession']->getId();
        $key=hash('sha256',$user.':'.$operationKey);
        ksort($params);
        $requestHash=hash('sha256',json_encode([$definition['method'],$params],JSON_THROW_ON_ERROR));
        try {
            // Reserve durably BEFORE native procedures (some commit their own transactions).
            $model->sql('INSERT INTO rv_api_operations(operation_key,user_id,request_hash,created_at) VALUES (?,?,?,?)',[$key,$user,$requestHash,time()]);
        } catch (\PDOException $error) {
            $existing=$model->sql('SELECT * FROM rv_api_operations WHERE operation_key=? AND user_id=?',[$key,$user])->fetch(\PDO::FETCH_ASSOC);
            if (! $existing) { throw $error; }
            if (! hash_equals($existing['request_hash'],$requestHash)) { return $this->error('KEY_CONFLICT','Use a new operation key for changed inputs.'); }
            if ($existing['result']===null) { return $this->error('OPERATION_PENDING','Check the object state; this key is reserved and will not execute again.'); }
            $stored=json_decode($existing['result'],true,512,JSON_THROW_ON_ERROR);
            return new CallToolResult([new TextContent(json_encode($stored))],isset($stored['error']),$stored);
        }
        $value=$this->container['rvApiModel']->invoke($definition['method'],$params);
        $result=$this->result($value);
        $model->sql('UPDATE rv_api_operations SET result=? WHERE operation_key=?',[json_encode($result->structuredContent,JSON_THROW_ON_ERROR),$key]);
        $model->audit('mcp.'.$definition['method'],$user);
        return $result;
    }

    private function result(mixed $value): CallToolResult
    {
        if ($value===false || $value===0) { return $this->error('NOT_APPLIED','The API rejected the input or the object is unavailable.'); }
        $content=['result'=>$value];
        return new CallToolResult([new TextContent(json_encode($content,JSON_THROW_ON_ERROR))],false,$content);
    }
    private function error(string $code,string $message): CallToolResult
    {
        $content=['error'=>['code'=>$code,'message'=>$message]];
        return new CallToolResult([new TextContent(json_encode($content))],true,$content);
    }
}
