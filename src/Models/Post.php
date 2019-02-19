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

    public function getBodyAttribute($value)
    {
        $doc = new DOMDocument();
        $doc->loadHTML("<?xml encoding=\"utf-8\" ?><div id='_content'>$value</div>");
        $this->defer($doc, 'img', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->defer($doc, 'iframe', 'about:blank');
        return $doc->saveHTML($doc->getElementById('_content'));
    }
}
