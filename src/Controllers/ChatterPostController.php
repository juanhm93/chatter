<?php

namespace DevDojo\Chatter\Controllers;

use Auth;
use Carbon\Carbon;
use DevDojo\Chatter\Events\ChatterAfterNewResponse;
use DevDojo\Chatter\Events\ChatterBeforeNewResponse;
use DevDojo\Chatter\Mail\ChatterDiscussionUpdated;
use DevDojo\Chatter\Models\Models;
use Event;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as Controller;
use Illuminate\Support\Facades\Mail;
use Purifier;
use Validator;

use App\Mail\NotificationEmail;

use DevDojo\Chatter\Requests\PostRequest;

class ChatterPostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // This is another unused route
        // we return an empty array to not expose user data to the public
        return response()->json([]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(PostRequest $request)
    {
        Event::dispatch(new ChatterBeforeNewResponse($request));

        if (config('chatter.security.limit_time_between_posts')) {
            if ($this->notEnoughTimeBetweenPosts()) {
                $minutes = trans_choice('chatter::messages.words.minutes', config('chatter.security.time_between_posts'));
                $chatter_alert = [
                    'chatter_alert_type' => 'danger',
                    'chatter_alert'      => trans('chatter::alert.danger.reason.prevent_spam', [
                                                'minutes' => $minutes,
                                            ]),
                ];
                return back()->with($chatter_alert)->withInput();
            }
        }

        $request->request->add(['user_id' => Auth::user()->id]);

        if (config('chatter.editor') == 'simplemde') {
            $request->request->add(['markdown' => 1]);
        }

        $new_post = Models::post()->create($request->all());
        $discussion = Models::discussion()->find($request->chatter_discussion_id);
        $category = Models::category()->find($discussion->chatter_category_id);

        if (!isset($category->slug)) {
            $category = Models::category()->first();
        }

        if ($new_post->id) {
            $discussion->last_reply_at = $discussion->freshTimestamp();
            $discussion->save();
            
            Event::dispatch(new ChatterAfterNewResponse($request, $new_post));
            if (function_exists('chatter_after_new_response')) {
                chatter_after_new_response($request);
            }
            
            // if email notifications are enabled
            if (config('chatter.email.enabled')) {
                // Send email notifications about new post
                $this->sendEmailNotifications($new_post->discussion);
            }
            $this->sendEmailNotificationsAdmin($new_post->discussion);

            $chatter_alert = [
                'chatter_alert_type' => 'success',
                'chatter_alert'      => trans('chatter::alert.success.reason.submitted_to_post'),
                ];

            return redirect('/'.config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$category->slug.'/'.$discussion->slug)->with($chatter_alert);
        } else {
            $chatter_alert = [
                'chatter_alert_type' => 'danger',
                'chatter_alert'      => trans('chatter::alert.danger.reason.trouble'),
                ];

            return redirect('/'.config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$category->slug.'/'.$discussion->slug)->with($chatter_alert);
        }
    }

    private function notEnoughTimeBetweenPosts()
    {
        $user = Auth::user();

        $past = Carbon::now()->subMinutes(config('chatter.security.time_between_posts'));

        $last_post = Models::post()->where('user_id', '=', $user->id)->where('created_at', '>=', $past)->first();

        if (isset($last_post)) {
            return true;
        }

        return false;
    }

    private function sendEmailNotifications($discussion)
    {
        
        $users = $discussion->users->except(Auth::user()->id);
        
        foreach ($users as $user) {
            //Mail::to($user)->queue(new ChatterDiscussionUpdated($discussion));
            $data = [
                'type'  => 'notification',
                'emailTo'   =>  $user->email,
                //'emailFrom'   =>  $request->email,
                'subject'  => 'Conversaciones Vivetix: Nuevo mensaje',
                //'nameTo'  => $event->user->first_name,
                'nameFrom'  => 'Vivetix',
                'bodyIntro'  => 'Tienes una nueva contestación a tu pregunta',
                //'message'  => '',
                'actionText'  => 'Ir a la conversación',
                'actionLink'  => url(config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$discussion->category->slug.'/'.$discussion->slug),
                'unsubscribeUrl'  => url('/') .'/'.config('chatter.routes.home') .'/'.config('chatter.routes.discussion').'/'.$discussion->category->slug.'/'.$discussion->slug,
                'unsubscribeText'  => trans('chatter::email.unsuscribe.link'),
            ];

            Mail::send(new NotificationEmail($data));
        }
    }
    private function sendEmailNotificationsAdmin($discussion)
    {
        $users = User::where("roles",'like','%admin%')->get();
        
        foreach ($users as $user) {
            $data = [
                'type'  => 'notification',
                'emailTo'   =>  $user->email,
                'subject'  => 'Conversaciones Vivetix: Nuevo mensaje',
                'nameFrom'  => 'Vivetix',
                'bodyIntro'  => 'Se creo un nueva pregunta o mensaje en el foro',
                'actionText'  => 'Ir a la conversación',
                'actionLink'  => url(config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$discussion->category->slug.'/'.$discussion->slug),
                'unsubscribeUrl'  => url('/') .'/'.config('chatter.routes.home') .'/'.config('chatter.routes.discussion').'/'.$discussion->category->slug.'/'.$discussion->slug,
                'unsubscribeText'  => trans('chatter::email.unsuscribe.link'),
            ];

            Mail::send(new NotificationEmail($data));
        }
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(PostRequest $request, $id)
    {
        $stripped_tags_body = ['body' => strip_tags($request->body)];

        $post = Models::post()->find($id);
        if (!Auth::guest() && ((Auth::user()->id == $post->user_id) || auth()->user()->isRole('admin'))) {
            if ($post->markdown) {
                $post->body = $request->body;
            } else {
                $post->body = Purifier::clean($request->body);
            }
            $post->save();

            $discussion = Models::discussion()->find($post->chatter_discussion_id);

            $category = Models::category()->find($discussion->chatter_category_id);
            if (!isset($category->slug)) {
                $category = Models::category()->first();
            }

            $chatter_alert = [
                'chatter_alert_type' => 'success',
                'chatter_alert'      => trans('chatter::alert.success.reason.updated_post'),
                ];

            return redirect('/'.config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$category->slug.'/'.$discussion->slug)->with($chatter_alert);
        } else {
            $chatter_alert = [
                'chatter_alert_type' => 'danger',
                'chatter_alert'      => trans('chatter::alert.danger.reason.update_post'),
                ];

            return redirect('/'.config('chatter.routes.home'))->with($chatter_alert);
        }
    }

    /**
     * Delete post.
     *
     * @param string $id
     * @param  \Illuminate\Http\Request
     *
     * @return \Illuminate\Routing\Redirect
     */
    public function destroy($id, Request $request)
    {
        $post = Models::post()->with('discussion')->findOrFail($id);

        if (($request->user()->id !== (int) $post->user_id) && !auth()->user()->isRole('admin')) {
            return redirect('/'.config('chatter.routes.home'))->with([
                'chatter_alert_type' => 'danger',
                'chatter_alert'      => trans('chatter::alert.danger.reason.destroy_post'),
            ]);
        }

        if ($post->discussion->posts()->oldest()->first()->id === $post->id) {
            if (config('chatter.soft_deletes')) {
                $post->discussion->posts()->delete();
                $post->discussion()->delete();
            } else {
                $post->discussion->posts()->forceDelete();
                $post->discussion()->forceDelete();
            }

            return redirect('/'.config('chatter.routes.home'))->with([
                'chatter_alert_type' => 'success',
                'chatter_alert'      => trans('chatter::alert.success.reason.destroy_post'),
            ]);
        }

        $post->delete();

        $url = '/'.config('chatter.routes.home').'/'.config('chatter.routes.discussion').'/'.$post->discussion->category->slug.'/'.$post->discussion->slug;

        return redirect($url)->with([
            'chatter_alert_type' => 'success',
            'chatter_alert'      => trans('chatter::alert.success.reason.destroy_from_discussion'),
        ]);
    }
}
