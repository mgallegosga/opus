<?php

namespace App\Models;

use Auth;
use Baum\Node;
use Carbon\Carbon;
use App\Models\Wiki;
use Illuminate\Support\Facades\DB;
use Cviebrock\EloquentSluggable\Sluggable;

/**
 * Class WikiPage
 *
 * @author Zeeshan Ahmed <ziishaned@gmail.com>
 * @package App\Models
 */
class WikiPage extends Node
{
    use Sluggable;

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    /**
     * @var \App\Models\Wiki
     */
    protected $wiki;

    /**
     * @var string
     */
    protected $table = 'wiki_page';

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'outline',
        'description',
        'parent_id',
        'user_id',
        'wiki_id',
        'created_at',
        'updated_at',
        'lft',
        'rgt',
        'depth',
    ];

    /**
     * @return mixed
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'page_id', 'id')->latest()->with('user');
    }    

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function wiki() {
        return $this->belongsTo(Wiki::class, 'wiki_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * Retrieve all the pages of a wiki from database.
     *
     * @param $wikiId
     * @return mixed
     */
    public function getPages($wikiId)
    {
        $query = $this;
        $query = $query->where('wiki_page.wiki_id', '=', $wikiId);
        $query = $query->with(['wiki'])->get();
        if(!$query) {
            return false;
        }
        return $query;
    }

    public function getRootPages($organization, $category, $wiki)
    {
        $nodes = [];

        $roots = $this->roots()->where('wiki_id', '=', $wiki->id)->get();
        foreach ($roots as $key => $value) {
            if($wiki->id == $value->wiki_id) {
                $nodes[] = [
                    'id'   => $value->id,
                    'wiki_id' => $value->wiki_id,
                    'text' => $value->name,
                    'slug' => $value->slug,
                    'children' => ($value->isLeaf() == false) ? true : false,
                    'data' => [
                        'created_at' => $value->created_at,
                        'slug' => $value->slug,
                    ],
                    'a_attr' => [
                        'href' => route('pages.show', [$organization->slug, $category->slug, $wiki->slug, $value->slug]),
                    ],
                ];
            }
        }

        return $nodes;
    }

    public function getChildrenPages($organization, $category, $wiki, $page)
    {
        $nodes = [];

        $childrens = $this->find($page->id)->children()->get();
        foreach ($childrens as $key => $value) {
            $nodes[] = [
                'id'   => $value->id,
                'wiki_id' => $value->wiki_id,
                'text' => $value->name,
                'slug' => $value->slug,
                'children' => ($value->isLeaf() == false) ? true : false,
                'data' => [
                    'created_at' => $value->created_at,
                    'slug' => $value->slug,
                ],
                'a_attr' => [
                    'href' => route('pages.show', [$organization->slug, $category->slug, $wiki->slug, $value->slug]),
                ],
            ];
        }

        return $nodes;   
    }

    public function getTreeTo($organization, $category, $wiki, $page)
    {
        $nodes = $this->find($page->id)->getAncestorsAndSelf()->toHierarchy();   
        return $nodes;
    }

    /**
     * Filter wiki pges where wiki pages with a specific id.
     *
     * @param  int    $wikiId
     * @param  string $text
     * @return mixed
     */
    public function filterWikiPages($wikiId, $text)
    {
        $query = $this->where('wiki_id', '=', $wikiId)->where('name', 'like', '%' . $text . '%')->get();
        return $query;
    }

    /**
     * Create a new resource.
     *
     * @param  string $wikiSlug
     * @param  array  $data
     * @return static
     */
    public function saveWikiPage($wikiId, $data)
    {
        $page = $this->create([
            'name'         =>  $data['page_name'],
            'outline'      =>  !empty($data['outline']) ? $data['outline'] : null,
            'description'  =>  !empty($data['page_description']) ? $data['page_description'] : null,
            'parent_id'    =>  !empty($data['page_parent']) ? $data['page_parent'] : null,
            'user_id'      =>  Auth::user()->id,
            'wiki_id'      =>  $wikiId,
        ]);

        return $page;
    }

    /**
     * Get a specific resource.
     *
     * @param string $slug
     * @return bool
     */
    public function getPage($slug)
    {
        $page = $this->where('slug', '=', $slug)->where('user_id', '=', Auth::user()->id)->with(['comments', 'wiki'])->first();
        if(is_null($page)) {
            return false;
        }
        return $page;
    }

    /**
     * Update a specific resource.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function updatePage($id, $data)
    {
        $this->find($id)->update([
            'name' => $data['page_name'],
            'description' => $data['page_description'],
            'outline' => $data['outline'],
            'parent_id'    =>  !empty($data['page_parent']) ? $data['page_parent'] : null,
        ]);
    
        return true;
    }

    /**
     * Delete a specific resource.
     *
     * @param  int $id
     * @return bool
     */
    public function deletePage($slug)
    {
        $query = $this->where('slug', '=', $slug)->delete();

        if(!$query) {
            return false;
        }
        return true;
    }

    /**
     * Star a specific resource.
     *
     * @param  int $id
     * @return bool
     */
    public function star($id)
    {
        $star = DB::table('user_star')
                    ->where('entity_id', '=', $id)
                    ->where('user_id', '=', Auth::user()->id)
                    ->where('entity_type', '=', 'page')
                    ->first();

        if(is_null($star)) {
            DB::table('user_star')->insert([
                'entity_id'     => $id,
                'entity_type'   => 'page',
                'user_id'       => Auth::user()->id,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]);
            return true;
        }
        DB::table('user_star')
            ->where('entity_id', '=', $id)
            ->where('user_id', '=', Auth::user()->id)
            ->where('entity_type', '=', 'page')
            ->delete();
        return false;
    }

    public function watch($id)
    {
        $watch = DB::table('user_watch')
                           ->where('entity_id', '=', $id)
                           ->where('user_id', '=', Auth::user()->id)
                           ->where('entity_type', '=', 'page')
                           ->first();

        if(is_null($watch)) {
            DB::table('user_watch')->insert([
                'entity_id'     => $id,
                'entity_type'   => 'page',
                'user_id'       => Auth::user()->id,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]);
            return true;
        }
        DB::table('user_watch')
            ->where('entity_id', '=', $id)
            ->where('user_id', '=', Auth::user()->id)
            ->where('entity_type', '=', 'page')
            ->delete();
        return false;
    }

    /**
     * Change the parent of a page.
     *
     * @param  array $data
     * @return bool
     */
    public function changePageParent($nodeId, $parentId)
    {
        $node   = $this->find($nodeId);
        if ($parentId == '#') {
            $parent = 'null';
            $node->makeRoot();
        } else {
            $parent = $this->find($parentId);
            $node->makeChildOf($parent);
        }
        return true;   
    }
}
