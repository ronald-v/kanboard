<?php
$private=realpath(__DIR__.'/../../.runtime/projecta/private');
if (! $private || strpos($private,realpath(__DIR__.'/../../.runtime'))!==0) { exit(2); }
$config=$private.'/smtp-openssl.cnf';
file_put_contents($config,"[req]\ndistinguished_name=dn\n[dn]\n[v3_ca]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n[v3_server]\nbasicConstraints=CA:FALSE\nsubjectAltName=DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\n");
$options=['config'=>$config,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048,'digest_alg'=>'sha256'];
$caKey=openssl_pkey_new($options);
$caCsr=openssl_csr_new(['commonName'=>'Projecta synthetic test CA'],$caKey,$options);
$ca=openssl_csr_sign($caCsr,null,$caKey,1,$options+['x509_extensions'=>'v3_ca']);
$serverKey=openssl_pkey_new($options);
$serverCsr=openssl_csr_new(['commonName'=>'localhost'],$serverKey,$options);
$server=openssl_csr_sign($serverCsr,$ca,$caKey,1,$options+['x509_extensions'=>'v3_server']);
if (! $ca || ! $server) { throw new RuntimeException('Synthetic certificate generation failed'); }
openssl_x509_export($ca,$caPem); openssl_x509_export($server,$serverPem);
openssl_pkey_export($serverKey,$serverKeyPem,null,$options);
file_put_contents($private.'/smtp-ca.pem',$caPem);
file_put_contents($private.'/smtp-server.pem',$serverPem);
file_put_contents($private.'/smtp-server-key.pem',$serverKeyPem);
echo "PASS synthetic SMTP certificates generated\n";
