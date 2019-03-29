<?php

namespace Sphere\Api;

class Error
{
    // HTTP errors
    const BAD_REQUEST = 400;
    const NOT_FOUND = 404;

    // Auth errors
    const AUTH_UNAUTORIZED = 5000;
    const AUTH_WRONG_LOGIN_OR_PASSWORD = 5001;

    // REST errors
    const REST_CREATE_EMPTY_DATA = 5100;
    const REST_VALIDATION_EXCEPTION = 5101;
    const REST_DELETE_RECORD_ERROR = 5102;

    // Custom
    const TEST_FINISHED = 6000;


    // MySQL errors (QueryException)
    // Список всех ошибок:
    // http://mysql-python.sourceforge.net/MySQLdb-1.2.2/public/MySQLdb.constants.ER-module.html
    // http://allerrorcodes.ru/?s=1062&stype=mysql
    const MYSQL_DUP_ENTRY = 1062;
}
