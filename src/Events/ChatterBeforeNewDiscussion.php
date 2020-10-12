<?php

namespace DevDojo\Chatter\Events;

use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

class ChatterBeforeNewDiscussion
{
    /**
     * @var Request
     */
    public $request;

    /**
     * Constructor.
     *
     * @param Request   $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
