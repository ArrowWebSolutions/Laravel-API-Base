<?php namespace Arrow\ApiBase\Transformer;

use League\Fractal\TransformerAbstract;

class Message extends TransformerAbstract
{
    protected $availableIncludes = [];

    public function transform($message)
    {
        $errors = $message['errors'];
        $messages = [];
        //convert the message(s) into an array
        if (is_array($errors)) {
            $messages = collect($errors)->collapse()->toArray();
        } else {
            $messages[] = $errors;
        }


        // Get bootstrap style
        switch (strtolower($message['result']))
		{
			case 'error':
				$style = 'danger';
				break;
            case 'warning':
                $style = 'warning';
                break;
            case 'info':
                $style = 'info';
                break;
            default:
            	$style = 'success';
		}

        return [
            'notification' => [
                'result' => $message['result'],
                'style' => $style,
                'messages' => $messages,
                'message' => implode('<br>', $messages),
            ],
            'errors' => $errors
        ];
    }
}