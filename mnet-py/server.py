#!/usr/bin/python
#
# Copyright (C) 2010 Brett Heath-Wlaz
# Copyright (C) 2010 Majen.net Consulting
# Copyright (C) 2010 SAU16, Exeter, NH
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Author: Brett Heath-Wlaz

import config
import PyzoServer
import ShelveApp
import SocketServer, xmlrpclib
import logging
import logging.handlers
import base64
import datetime
import time
import re
import sys

sys.stderr = open(config.STDERRFILE, 'a', 0)
dbtime = time.time()
db = config.dbconnect()

logger = logging.getLogger()
logger.setLevel(logging.DEBUG)
logger.addHandler(logging.handlers.RotatingFileHandler(config.LOGFILE_BASE, maxBytes=config.LOGFILE_MAXBYTES, backupCount=config.LOGFILE_BACKUPS))

def logtime() :
  return time.asctime()

def sql_handler(environ, start_response) :
  global db
  global dbtime
  (sql,), method = xmlrpclib.loads(environ["wsgi.input"].read())

  logger.debug(logtime() + ": method: %s" % method)
  logger.debug(logtime() + ": sql: ( %s )" % sql)

  if not re.search('sql_execute$', method) :
    logger.debug("bad method: %s" % method)
    raise Exception("Unhandled method: %s" % method)

  if (config.DB_RECONNECT_SECONDS and ((time.time() - config.DB_RECONNECT_SECONDS) > dbtime)) :
    db.close()
    db = config.dbconnect()
    dbtime = time.time()
  cur = db.cursor()
  cur.execute(sql)
  res = xmlrpclib.dumps((tuple(tuple(base64.b64encode(fval) if type(fval) == str else base64.b64encode(str(fval)) if isinstance(fval, datetime.datetime) else base64.b64encode(str(fval)) if type(fval) == long else fval for fval in row) for row in cur),), methodresponse = 1, allow_none = 1)
  if config.DB_COMMIT :
    db.commit()
  start_response("200 OK", [])
  return res


if __name__ == "__main__" :
  SocketServer.TCPServer.allow_reuse_address = 1
  server = SocketServer.TCPServer(config.ADDRESS, PyzoServer.PyzoServer(ShelveApp.ShelveApp(sql_handler)))
  logger.debug(logtime() + ": starting server")
  server.serve_forever()
