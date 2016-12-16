<?php
namespace App\Service\Admin;
use App\Repositories\Eloquent\ArticleRepositoryEloquent;
use App\Repositories\Eloquent\CategoryRepositoryEloquent;
use App\Repositories\Eloquent\TagRepositoryEloquent;
use App\Traits\SendSystemErrorTrait;
use App\Traits\QiniuTrait;
use Exception;
/**
* 角色service
*/
class ArticleService{

	use SendSystemErrorTrait,QiniuTrait;
	protected $article;
	protected $category;
	protected $tag;

	function __construct(ArticleRepositoryEloquent $article,CategoryRepositoryEloquent $category,TagRepositoryEloquent $tag)
	{
		$this->article =  $article;
		$this->category =  $category;
		$this->tag =  $tag;
	}
	/**
	 * datatables获取数据
	 * @author 晚黎
	 * @date   2016-11-02T10:31:46+0800
	 * @return [type]                   [description]
	 */
	public function ajaxIndex()
	{
		// datatables请求次数
		$draw = request('draw', 1);
		// 开始条数
		$start = request('start', config('admin.golbal.list.start'));
		// 每页显示数目
		$length = request('length', config('admin.golbal.list.length'));
		// datatables是否启用模糊搜索
		$search['regex'] = request('search.regex', false);
		// 搜索框中的值
		$search['value'] = request('search.value', '');
		// 排序
		$order['name'] = request('columns.' .request('order.0.column',0) . '.name');
		$order['dir'] = request('order.0.dir','asc');

		$result = $this->article->getArticleList($start,$length,$search,$order);

		$articles = [];

		if ($result['articles']) {
			foreach ($result['articles'] as &$v) {
				$v->actionButton = $v->getArticleActionButton();
				$articles[] = $v;
			}
		}

		return [
			'draw' => $draw,
			'recordsTotal' => $result['count'],
			'recordsFiltered' => $result['count'],
			'data' => $articles,
		];
	}

	/**
	 * 添加视图
	 * @author 晚黎
	 * @date   2016-12-09T15:41:37+0800
	 * @return [type]                   [description]
	 */
	public function createView()
	{
		$categories = $this->category->getArticleCategories();
		$tags = $this->tag->allTags();
		return compact('categories','tags');
	}
	/**
	 * 添加文章
	 * @author 晚黎
	 * @date   2016-12-12T16:37:23+0800
	 * @param  [type]                   $attributes [description]
	 * @return [type]                               [description]
	 */
	public function storeArticle($request)
	{
		try {
			$attributes = $request->all();
			// 文章banner
			if ($request->hasFile('banner')) {
				$attributes['banner'] = $this->upload($request->file('banner'));
			}

			$attributes['content_html'] = $attributes['editor-html-code'];
			$article = $this->article->skipPresenter()->create($attributes);

			if ($article) {
				// 添加标签关系
				$tagIds = [];
				if (isset($attributes['new_tags']) && $attributes['new_tags']) {
					$tags = explode(',', $attributes['new_tags']);
					foreach ($tags as $v) {
						$tag = $this->tag->firstOrCreate(['name' => $v]);
						$tagIds[] = $tag['id'];
					}
				}

				$tagIds = array_unique(array_merge($tagIds,$attributes['tags']));
				$article->tag()->sync($tagIds);
				// 添加分类关系
				$article->category()->sync($attributes['cid']);
			}
			flash_info($article,trans('admin/alert.article.create_success'),trans('admin/alert.article.create_error'));
			return $article;
		} catch (Exception $e) {
			// 错误信息发送邮件
			$this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
			return false;
		}
	}
	/**
	 * 修改视图
	 * @author 晚黎
	 * @date   2016-12-13T14:10:42+0800
	 * @return [type]                   [description]
	 */
	public function editView($id)
	{
		$categories = $this->category->getArticleCategories();
		$tags = $this->tag->allTags();
		$article = $this->article->findArticleById($id);
		$article['id'] = $this->article->encodeId($article['id']);
		return compact('categories','tags','article');
	}

	/**
	 * 修改文章
	 * @author 晚黎
	 * @date   2016-12-13T17:27:19+0800
	 * @param  [type]                   $attributes [description]
	 * @param  [type]                   $id         [description]
	 * @return [type]                               [description]
	 */
	public function updateArticle($request,$id)
	{
		// 防止用户恶意修改表单id，如果id不一致直接跳转500
		$attributes = $request->all();
		if ($attributes['id'] != $id) {
			abort(500,trans('admin/errors.user_error'));
		}
		try {
			// 文章banner
			if ($request->hasFile('banner')) {
				$attributes['banner'] = $this->upload($request->file('banner'));
			}

			$attributes['content_html'] = $attributes['editor-html-code'];

			$id = $this->article->decodeId($id);
			$attributes['id'] = $this->article->decodeId($attributes['id']);

			$result = $this->article->update($attributes,$id);
			if ($result) {
				// 添加标签关系
				$tagIds = [];
				if (isset($attributes['new_tags']) && $attributes['new_tags']) {
					$tags = explode(',', $attributes['new_tags']);
					foreach ($tags as $v) {
						$tag = $this->tag->firstOrCreate(['name' => $v]);
						$tagIds[] = $tag['id'];
					}
				}
				$tagIds = array_unique(array_merge($tagIds,$attributes['tags']));
				$article->tag()->sync($tagIds);

				$article->category()->sync($attributes['cid']);
			}
			flash_info($result,trans('admin/alert.article.edit_success'),trans('admin/alert.article.edit_error'));
			return $result;
		} catch (Exception $e) {
			// 错误信息发送邮件
			$this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
			return false;
		}
	}
	/**
	 * 角色暂不做状态管理，直接删除
	 * @author 晚黎
	 * @date   2016-11-03T10:01:36+0800
	 * @param  [type]                   $id [权限id]
	 * @return [type]                       [Boolean]
	 */
	public function destroyArticle($id)
	{
		try {
			$result = $this->article->delete($this->article->decodeId($id));
			flash_info($result,trans('admin/alert.article.destroy_success'),trans('admin/alert.article.destroy_error'));
			return $result;
		} catch (Exception $e) {
			// 错误信息发送邮件
			$this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
			return false;
		}
		
	}

	/**
	 * 修改文章状态
	 * @author 晚黎
	 * @date   2016-12-14T10:12:55+0800
	 * @param  [type]                   $id     [description]
	 * @param  [type]                   $status [description]
	 * @return [type]                           [description]
	 */
	public function mark($id,$status)
	{
		try {
			$result = $this->article->update(['status' => $status],$this->article->decodeId($id));
			flash_info($result,trans('admin/alert.article.edit_success'),trans('admin/alert.article.edit_error'));
			return $result;
		} catch (Exception $e) {
			// 错误信息发送邮件
			$this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
			return false;
		}
	}
}