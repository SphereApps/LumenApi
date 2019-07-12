<?php

namespace Sphere\Api;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use \Illuminate\Database\QueryException;
use Laravel\Lumen\Exceptions\Handler;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Sphere\Api\Helpers\ResponseWrapper;
use Sphere\Api\Error;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionHandler extends Handler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    public function __construct()
    {
        return $this->response = new ResponseWrapper;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        }

        // if ($e instanceof HttpResponseException) {
        //     return $e->getResponse();
        // } elseif ($e instanceof ModelNotFoundException) {
        //     $e = new NotFoundHttpException($e->getMessage(), $e);
        // } elseif ($e instanceof AuthorizationException) {
        //     $e = new HttpException(403, $e->getMessage());
        // } elseif ($e instanceof ValidationException && $e->getResponse()) {
        //     return $e->getResponse();
        // }

        if ($e instanceof NotFoundHttpException) {
            return parent::render($request, $e);
        }

        switch (true) {
            case $e instanceof AuthorizationException:
                return $this->response->error(Error::AUTH_UNAUTORIZED);

            case $e instanceof ValidationException:
                return $this->response->error(Error::REST_VALIDATION_EXCEPTION, [
                    'message' => 'Переданы некорректные данные',
                    'validation' => $e->errors(),
                ]);

            case $e instanceof QueryException:
                $errorInfo = $e->errorInfo;

                return $this->response->error([
                    // http://mysql-python.sourceforge.net/MySQLdb-1.2.2/public/MySQLdb.constants.ER-module.html
                    'code' => $errorInfo[1] ?? $errorInfo,
                    'exception' => 'QueryException',
                    'message' => isset($errorInfo[2]) ? $errorInfo[2] : '',
                    'trace' => $this->getExceptionTrace($e),
                ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $result = [
                'exception' => class_basename(get_class($e)),
                'message' => $e->getMessage(),
            ];
            if (env('APP_DEBUG')) {
                if (method_exists($e, 'getFile')) {
                    $result['file'] = $e->getFile();
                    $result['line'] = $e->getLine();
                }
            }
            return $this->response->error($result);
        }


        return parent::render($request, $e);
    }

    protected function getExceptionTrace(Exception $e) {
        return collect($e->getTrace())->map(function($row){
            if ($row['file'] ?? false) {
                $row['file'] = ltrim($row['file'], base_path());
            }
            return array_only($row, ['file', 'line', 'function', 'class']);
        });
    }
}
