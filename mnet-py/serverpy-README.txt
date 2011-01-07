==========================
Deploying the MNet server
==========================

Install python 2.6.x
Install cx_Oracle (for python 2.6, non-unicode)
Install m2crypto (for python 2.6)
Put the following files in a folder:
  config.py
  mnet.py
  PyzoServer.py
  server.py
  ShelveApp.py

Initialize by running:
  python ShelveApp.py init <wwwroot>
Note that wwwroot should be the external address of the server, including http://
For example, http://myserver:12345

The server's cert will be in cert.txt. Use it to register the server on any client machine(s) via
whatever method they provide. Note that the serverpath is /

Register any clients by running:
  python ShelveApp.py addpeer <wwwroot> <serverpath> <certfile>
certfile should be a file containing the client's certificate

Edit config.py and modify settings as needed

Run the server:
  python server.py

==========================
API
==========================

The MNet server supports only one command via xmlrpc:
  sql_execute <sql>

This will run the given command verbatim in the DB and return the results as an array of arrays of values:
  [[col1, col2, col3, ...], [col1, col2, col3, ...], ...]

STRING NOTE: Due to limitations in the xml format, all string values are returned in base64 code
representation. It is up to the client to decode these strings.
