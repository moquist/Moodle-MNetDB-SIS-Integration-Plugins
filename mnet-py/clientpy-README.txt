==========================
Deploying the MNet client
==========================

Install python 2.6.x
Install m2crypto (for python 2.6)
Put the following files in a folder:
  config.py
  mnet.py
  client.py
  ShelveApp.py

Initialize by running:
  python ShelveApp.py init <wwwroot>
Note that wwwroot should be the external address of the client, including http://
For example, http://myserver:12345
However, the client shouldn't need to accept connections, so the address need not actually be accessible

The client's cert will be in cert.txt. Use it to register the client on the server machine (see server README.txt)
Note that the serverpath is /

Register the server by running:
  python ShelveApp.py addpeer <wwwroot> <serverpath> <certfile>
certfile should be a file containing the server's certificate

Edit config.py and modify settings as needed

Run the client:
  python client.py <wwwroot>
wwwroot is the server's wwwroot

==========================
Usage
==========================

The client will provide a prompt: ">". You can type sql commands at the prompt, and the result will be printed.