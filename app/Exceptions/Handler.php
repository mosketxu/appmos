<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Los errores de verdad (500) llegan solos a Alex y a Claude como tarea del TO-DO (ver App\Support\ErroresApp)
            if (! ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)
                && ! ($e instanceof \Illuminate\Validation\ValidationException)
                && ! ($e instanceof \Illuminate\Auth\AuthenticationException)
                && ! ($e instanceof \Illuminate\Auth\Access\AuthorizationException)
                && ! ($e instanceof \Illuminate\Session\TokenMismatchException)
                && ! ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException)) {
                \App\Support\ErroresApp::deExcepcion($e);
            }
        });
    }
}
