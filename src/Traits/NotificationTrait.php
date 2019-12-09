<?php namespace Arrow\ApiBase\Traits;

trait NotificationTrait
{
    use ApiResponseTrait;
    
    /**
     * Generates a success Response with a given message.
     *
     * @return  Response
     */
    protected function respondWithSuccessNotification($message, $httpcode = 200) 
    {
        return $this->setStatusCode($httpcode)->respondWithNotification('success', $message);
    }

    /**
     * Generates a info Response with a given message.
     *
     * @return  Response
     */
    protected function respondWithInfoNotification($message, $httpcode = 200) 
    {
        return $this->setStatusCode($httpcode)->respondWithNotification('info', $message);
    }
    
    /**
     * Generates a warning Response with a given message.
     *
     * @return  Response
     */
    protected function respondWithWarningNotification($message, $httpcode = 200) 
    {
        return $this->setStatusCode($httpcode)->respondWithNotification('warning', $message);
    }

    /**
     * Generates a error Response with a given message.
     *
     * @return  Response
     */
    protected function respondWithErrorNotification($message, $httpcode = 500) 
    {
        return $this->setStatusCode($httpcode)->respondWithNotification('error', $message);
    }

}