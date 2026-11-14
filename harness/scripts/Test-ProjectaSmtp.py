"""Receive a real MIME message over verified STARTTLS, entirely on loopback."""
from pathlib import Path
import os
import socketserver
import ssl
import subprocess
import threading

ROOT=Path(__file__).resolve().parents[2]
PRIVATE=ROOT/'.runtime/projecta/private'
messages=[]
class Handler(socketserver.BaseRequestHandler):
    def handle(self):
        connection=self.request
        def send(text): connection.sendall((text+'\r\n').encode())
        def line():
            value=b''
            while not value.endswith(b'\n'):
                chunk=connection.recv(1)
                if not chunk: return b''
                value+=chunk
            return value
        send('220 localhost Projecta synthetic SMTP')
        while True:
            command=line()
            if not command: break
            verb=command.split(b' ',1)[0].strip().upper()
            if verb in (b'EHLO',b'HELO'): send('250-localhost\r\n250 STARTTLS')
            elif verb==b'STARTTLS':
                send('220 Ready to start TLS')
                context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
                context.load_cert_chain(PRIVATE/'smtp-server.pem',PRIVATE/'smtp-server-key.pem')
                connection=context.wrap_socket(connection,server_side=True)
            elif verb in (b'MAIL',b'RCPT',b'RSET'): send('250 OK')
            elif verb==b'DATA':
                send('354 End data with a dot')
                data=[]
                while True:
                    value=line()
                    if value in (b'.\r\n',b''): break
                    data.append(value)
                assert isinstance(connection,ssl.SSLSocket)
                messages.append(b''.join(data))
                send('250 Message accepted')
            elif verb==b'QUIT': send('221 Bye'); break
            else: send('500 Unsupported test command')
class Server(socketserver.TCPServer): allow_reuse_address=True

environment=os.environ.copy()
environment.update(PROJECTA_SMTP_HOST='localhost',PROJECTA_SMTP_PORT='12587',PROJECTA_SMTP_ENCRYPTION='tls',PROJECTA_SMTP_FROM='sender@example.invalid',PROJECTA_SMTP_USER='',PROJECTA_SMTP_PASSWORD='',PROJECTA_TEST_CA_FILE=str(PRIVATE/'smtp-ca.pem'))
with Server(('127.0.0.1',12587),Handler) as server:
    thread=threading.Thread(target=server.serve_forever,daemon=True); thread.start()
    result=subprocess.run(['powershell','-NoProfile','-File','harness/scripts/ProjectaEnvironment.ps1','-Script','harness/scripts/Projecta-SmtpSend.php'],cwd=ROOT,env=environment,capture_output=True,text=True,timeout=30)
    server.shutdown(); thread.join(timeout=5)
    assert result.returncode==0, (result.stdout+result.stderr)[:800]
assert len(messages)==1, 'Expected exactly one SMTP delivery to the synthetic TLS receiver'
assert b'recipient@example.invalid' in messages[0] and b'Projecta' in messages[0]
print('PASS SMTP: one MIME message delivered to a loopback STARTTLS receiver with certificate validation; no real recipient or external SMTP used.')
