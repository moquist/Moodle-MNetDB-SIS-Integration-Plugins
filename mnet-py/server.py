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
import base64
import datetime
import re
logging.basicConfig()

def sql_handler(environ, start_response) :
  (sql,), method = xmlrpclib.loads(environ["wsgi.input"].read())

  if not re.search('sql_execute$', method) :
    raise Exception("Unhandled method: %s" % method)
  cur = config.db.cursor()
  cur.execute(sql)
  res = xmlrpclib.dumps((tuple(tuple(base64.b64encode(fval) if type(fval) == str else base64.b64encode(str(fval)) if isinstance(fval, datetime.datetime) else base64.b64encode(str(fval)) if type(fval) == long else fval for fval in row) for row in cur),), methodresponse = 1, allow_none = 1)
  if config.COMMIT :
    config.db.commit()
  start_response("200 OK", [])
  return res


if __name__ == "__main__" :
  SocketServer.TCPServer.allow_reuse_address = 1
  server = SocketServer.TCPServer(config.ADDRESS, PyzoServer.PyzoServer(ShelveApp.ShelveApp(sql_handler)))
  server.serve_forever()
