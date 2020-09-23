<?php

namespace DevDojo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Masterminds\HTML5;
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

    protected function defer(DOMDocument $doc, $tag, $src_blank)
    {
        $list = $doc->getElementsByTagName($tag);
        foreach ($list as $i) {
            if ($src = $i->getAttribute('src')) {
                $i->setAttribute('src', $src_blank);
                $i->setAttribute('data-src', $src);
                $class =  $i->getAttribute('class');
                $class = $class ? $class : '';
                $i->setAttribute('class', trim("$class lazy"));
                $i->setAttribute('data-src', $src);
            }
        }
    }

    public function setBodyAttribute($value)
    {
        $html = "<html><head><title>TEST</title></head><body><div id='_content'>$value</div></body></html>";
        $html5 = new HTML5();
        $doc = $html5->loadHTML($html);
        $this->defer($doc, 'img', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->defer($doc, 'iframe', 'about:blank');
        $this->attributes['body'] = $html5->saveHTML($doc->getElementById('_content'));
    }
}
