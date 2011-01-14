# This configuration file is used by client.py, server.py, and server-wrapper.py.
#
# You'll need a copy of this directory and a separate config.py for each
# separate client or server you wish to run.

#######################################################
# START: client.py & server.py shared
FILE_APP = "app.db" # You probably don't want to change this.
FILE_PEERS = "peers.db" # You probably don't want to change this.
FILE_CERT = "cert.txt" # You probably don't want to change this.

# If server.py: Where do we listen?
# If client.py: Where do we ask?
ADDRESS = ("localhost", 60080) # You might want to change this.

# END: client.py & server.py shared
#######################################################
# START: server.py

# You definitely want to change this if you're configuring server.py.
DB_NAME = "somedatabase"
DB_USER = "someuser"
DB_PASS = "somepass"

# If your DB connection is buggy, automatically disconnect and reconnect after this many seconds.
# Leaving 0 here tells server.py never to disconnect from the DB.
DB_RECONNECT_SECONDS = 0

# Some databases (e.g., MSSQL) require explicit commits. Only set this to 1 if:
# 1. You're using server.py to write to the database (!!) and
# 2. You know you need to commit explicitly.
DB_COMMIT = 0

# What should the base name of the server.py logs be?
LOGFILE_BASE = "c:\\temp\\mnet-server.log"
# How many server.py logs should be kept in rotation?
LOGFILE_BACKUPS = 5
# How big can the logfile get before rotating?
LOGFILE_MAXBYTES = 1048576

STDERRFILE = "c:\\temp\\mnet-server.err"


#### REQUIRED ####
# You must un-comment one of the following database sections

#####################
# DATABASE: Oracle
# To create a read-only user for your Oracle database, you may wish to see http://arjudba.blogspot.com/2008/09/how-to-make-global-read-only-user.html .
# In particular, you probably want to do something like this (note that the password is not quoted):
# c:\> sqlplus / as sysdba
# sqlplus> create user <username> identified by <password>;
# sqlplus> grant create session,select any dictionary,select any table to <username>;
#
# Uncomment this for Oracle:
# def dbconnect() :
#   import cx_Oracle
#   return cx_Oracle.connect("%s/%s@%s" % (DB_USER, DB_PASS, DB_NAME))

#####################
# DATABASE: MS SQL
# Uncomment this for mssql:
# def dbconnect() :
#   import pymssql
#   return pymssql.connect(user = DB_USER, password = DB_PASS, database = DB_NAME)


#####################
# DATABASE: Postgres
# def dbconnect() :
#   import psycopg2
#   return psycopg2.connect(user = DB_USER, password = DB_PASS, database = DB_NAME)


# END: server.py
#######################################################
# START: server-wrapper.py
# Windows only, for the server-wrapper.
#
# If you need the silly server-wrapper, make sure these paths will do what you
# need/want for your system.
WINDIR = "c:\\mnet-py"
WINCMD = "c:\\python26\\python.exe server.py"
PIDFILE = "c:\\temp\\mnet-server.pid"
