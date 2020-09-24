<?php
namespace DevDojo\Chatter\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

use App\Event;
use App\Ticket;
use Log;
use App\EventOption;
use DB;
use SpamScore;

use DevDojo\Chatter\Requests\PostRequest;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function withValidator($validator)
    {
        $req = $this;
        $validator->after(function ($validator) use ($req) {
            $score = SpamScore::score("{$req->body}");
            if ($score > 99) {
                 return $validator
                    ->errors()
                    ->add('token', __('Hemos detectado que este post puediera ser spam, si no lo fuera por favor contáctanos'));
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'body' => 'required|min:10',
        ];
    }

    public function messages()
    {
        return [
            'body.required' => trans('chatter::alert.danger.reason.content_required'),
            'body.min' => trans('chatter::alert.danger.reason.content_min'),
        ];
    }
}
