<?php namespace Folklore\Pages\Models;

use Folklore\EloquentLocalizable\LocalizableTrait;
use Folklore\EloquentPicturable\PicturableTrait;

use Cviebrock\EloquentTaggable\Taggable;
use Cviebrock\EloquentTaggable\TaggableImpl;

class Page extends Model implements Taggable {

	use LocalizableTrait, PicturableTrait, TaggableImpl;

	protected $table = 'pages';

	protected $fillable = array(
		'handle',
		'type',
		'properties',
		'published',
		'publish_at'
	);

	protected $softDelete = true;
	
	protected $dates = ['publish_at', 'deleted_at', 'created_at', 'updated_at'];

	/*
	 *
	 * Relationships
	 *
	 */
	public function parent()
	{
		return $this->belongsTo('Folklore\Pages\Models\Page','parent_id');
	}
	public function categories()
	{
		return $this->morphToMany('Folklore\Pages\Models\Category', 'categorizable')
					->withTimestamps();
	}
	public function blocks()
	{
		return $this->hasMany('Folklore\Pages\Models\Block','page_id')
					->orderBy('order','asc');
	}

	/*
	 *
	 * Scopes
	 *
	 */
	public function scopePublished($query)
	{
		return $query->where('published', 1)
					->where(function($query) {
						$query->whereNull('publish_at');
						$query->orWhere('publish_at', '<=', Carbon::now()->toDateTimeString());
					});
	}
	public function scopeNotPublished($query)
	{
		return $query->where('published', 0)
					->orWhere(function($query) {
						$query->whereNotNull('publish_at');
						$query->where('publish_at', '>', Carbon::now()->toDateTimeString());
					});
	}
	public function scopeWithParentLocale($query, $locale)
	{
		return $query->with(array('parent.locale' => function($query) use ($locale)
		{
			$query->where('locale', '=', $locale);
		}));
	}
	public function scopeWithBlocksLocale($query, $locale)
	{
		return $query->with(array('blocks.locale' => function($query) use ($locale)
		{
			$query->where('locale', '=', $locale);
		}));
	}
	public function scopeWithCategoriesLocale($query, $locale)
	{
		return $query->with(array('categories.locale' => function($query) use ($locale)
		{
			$query->where('locale', '=', $locale);
		}));
	}

	/*
	*
	* Accessors and mutators
	*
	*/
	protected function setPropertiesAttribute($value)
	{
		$this->attributes['properties'] = !is_string($value) ? json_encode($value):$value;
	}
	protected function getPropertiesAttribute($value)
	{
		if(empty($value)) {
			return new \StdClass();
		}
		return is_string($value) ? @json_decode($value):$value;
	}

	/*
	*
	* Get Methods
	*
	*/
	public function blocksForArea($area) {

		$blocks = $this->blocks->filter(function($item) use ($area)
		{
		    return $item->area === $area;
		});

		return $blocks;
	}

	/*
	*
	* Sync methods
	*
	*/
	public function syncBlocks($blocks = array())
	{
		//Save blocks
		if(is_array($blocks) && sizeof($blocks)) {
			$ids = array();
			foreach($blocks as $block)
			{
				$blockModel = isset($block['id']) && (int)$block['id'] > 0 ? Block::find($block['id']):new Block();
				if(!$blockModel)
				{
					continue;
				}
				$blockModel->fill($block);
				$blockModel->order = sizeof($ids);
				$blockModel->save();
				$this->blocks()->save($blockModel);
				$ids[] = $blockModel->id;

				//Sync block locales
				$blockModel->syncLocales(isset($block['locales']) ? $block['locales']:array());

				//Sync block pictures
				$blockModel->syncPictures(isset($block['pictures']) ? $block['pictures']:array());
			}

			$blocksToDelete = $this->blocks()->whereNotIn('id',$ids)->get();
			foreach($blocksToDelete as $block) {
				$block->delete();
			}
		} else {
			foreach($this->blocks as $block) {
				$block->delete();
			}
		}
	}

}

Page::created(function($item)
{
	if(empty($item->handle)) {
		$item->handle = $item->type.'_'.$item->id;
		$item->save();
	}
});
