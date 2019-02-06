<?php

namespace DevDojo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DOMDocument;

class Post extends Model
{
    use SoftDeletes;
    
    protected $table = 'chatter_post';
    public $timestamps = true;
    protected $fillable = ['chatter_discussion_id', 'user_id', 'body', 'markdown'];
    protected $dates = ['deleted_at'];

    public function discussion()
    {
        return $this->belongsTo(Models::className(Discussion::class), 'chatter_discussion_id');
    }

    public function user()
    {
        return $this->belongsTo(config('chatter.user.namespace'));
    }

    public function getBodyAttribute($value)
    {
        $doc = new DOMDocument();
        $doc->loadHTML("<?xml encoding=\"utf-8\" ?><div id='_content'>$value</div>");
        $list = $doc->getElementsByTagName('img');
        foreach ($list as $i) {
            if ($src = $i->getAttribute('src')) {
                $i->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=');
                $i->setAttribute('data-src', $src);
                $class =  $i->getAttribute('class');
                $class = $class ? $class : '';
                $i->setAttribute('class', "$class lazy");
                $i->setAttribute('data-src', $src);
            }
        }
        return $doc->saveHTML($doc->getElementById('_content'));
    }
}
