<?php
/**
 * @author: nguyentinh
 * @create: 06/20/20, 8:21 PM
 */

namespace TinhPHP\Woocommerce\Http\Controllers\Admin;

use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use TinhPHP\Woocommerce\Models\Product;
use TinhPHP\Woocommerce\Models\ProductImage;
use App\Models\RolePermission;
use App\Services\MediaService;
use TinhPHP\Woocommerce\Services\ProductCategoryService;
use \TinhPHP\Woocommerce\Services\ProductService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Class ProductController.
 *
 * @property ProductService $productService
 * @property ProductCategoryService $productCategoryService
 * @property MediaService $mediaService
 */
final class ProductController extends AdminWoocommerceController
{
    public function __construct(
        ProductService $productService,
        ProductCategoryService $productCategoryService,
        MediaService $mediaService
    ) {
        parent::__construct();
        $this->middleware(['permission:' . RolePermission::PRODUCT_SHOW]);

        $this->mediaService = $mediaService;
        $this->productService = $productService;
        $this->productCategoryService = $productCategoryService;
    }

    /**
     * @param Request $request
     * @return Factory|View
     */
    public function index(Request $request)
    {
        $this->productService->buildCondition($request->all(), $condition, $sortBy, $sortType);
        $items = Product::query()->where($condition)->orderBy($sortBy, $sortType)->paginate($this->page_number);

        $filter = $this->productService->filter($request->all());

        $data = [
            'title' => trans('common.list') . ' ' . trans('nav.menu_left.products'),
            'filter' => $filter,
            'items' => $items,
        ];

        return view('view_woocommerce::admin.product.index', $this->render($data));
    }

    public function create()
    {
        $data = [
            'title' => trans('common.add') . ' ' . trans('nav.menu_left.products'),
            'product' => new Product(),
            'dropdownCategory' => $this->productCategoryService->dropdown(),
        ];

        return view('view_woocommerce::admin.product.form', $this->render($data));
    }

    public function store(Request $request)
    {
        $params = $request->all();
        if (!empty($params['_token'])) {
            $result = $this->productService->create($params);

            if (empty($result['message'])) {
                // image
                if ($request->hasFile('file')) {
                    $upload = $this->mediaService->upload(
                        $request->file('file'),
                        [
                            'object_type' => Media::OBJECT_TYPE_PRODUCT,
                            'object_id' => $result['id'],
                        ]
                    );
                    if (!empty($upload['content']['id'])) {
                        $myObject = Product::query()->find($result['id']);
                        $myObject->image_id = $upload['content']['id'];
                        $myObject->image_url = $upload['content']['file_name'];
                        $myObject->save();
                    }
                }

                // gallery
                if ($request->hasFile('file_multi')) {
                    foreach ($request->file('file_multi') as $file) {
                        $resultUpload = $this->mediaService->upload(
                            $file,
                            [
                                'object_type' => Media::OBJECT_TYPE_PRODUCT,
                                'object_id' => $result['id'],
                            ]
                        );
                        if (!empty($resultUpload['content']['id'])) {
                            ProductImage::query()->create(
                                [
                                    'product_id' => $result['id'],
                                    'image_id' => $resultUpload['content']['id'],
                                    'image_url' => $resultUpload['content']['file_name'],
                                    'creator_id' => Auth::id(),
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]
                            );
                        }
                    }
                }

                $request->session()->flash('success', trans('common.add.success'));

                return redirect(admin_url('woocommerce/products'), 302);
            } else {
                $request->session()->flash('error', $result['message']);
            }
        }

        return back()->withInput();
    }

    public function show($id)
    {
        return redirect(admin_url('woocommerce/products/' . $id . '/edit'), 302);
    }

    public function edit($id)
    {
        $data = [
            'title' => trans('common.edit') . ' ' . trans('nav.menu_left.products'),
            'dropdownCategory' => $this->productCategoryService->dropdown(),
            'product' => Product::query()->findOrFail($id),
        ];

        return view('view_woocommerce::admin.product.form', $this->render($data));
    }

    public function update($id, Request $request)
    {
        $params = $request->all();
        if (!empty($params['_token'])) {
            // remove image
            if (!empty($params['file_remove'])) {
                $params['image_id'] = 0;
                $params['image_url'] = '';
            }

            $result = $this->productService->update($id, $params);

            if (empty($result['message'])) {
                // image
                if ($request->hasFile('file')) {
                    $upload = $this->mediaService->upload(
                        $request->file('file'),
                        [
                            'object_type' => Media::OBJECT_TYPE_PRODUCT,
                            'object_id' => $id,
                        ]
                    );
                    if (!empty($upload['content']['id'])) {
                        $myObject = Product::query()->find($id);
                        $myObject->image_id = $upload['content']['id'];
                        $myObject->image_url = $upload['content']['file_name'];
                        $myObject->save();
                    }
                }

                // gallery
                if ($request->hasFile('file_multi')) {
                    foreach ($request->file('file_multi') as $file) {
                        $resultUpload = $this->mediaService->upload(
                            $file,
                            [
                                'object_type' => Media::OBJECT_TYPE_PRODUCT,
                                'object_id' => $id,
                            ]
                        );
                        if (!empty($resultUpload['content']['id'])) {
                            ProductImage::query()->create(
                                [
                                    'product_id' => $id,
                                    'image_id' => $resultUpload['content']['id'],
                                    'image_url' => $resultUpload['content']['file_name'],
                                    'creator_id' => Auth::id(),
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]
                            );
                        }
                    }
                }

                // remove gallery
                if (!empty($params['file_multi_remove'])) {
                    ProductImage::query()->whereIn('id', $params['file_multi_remove'])->delete();
                }

                $request->session()->flash('success', trans('common.edit.success'));

                return redirect(admin_url('woocommerce/products'), 302);
            } else {
                $request->session()->flash('error', $result['message']);
            }
        }

        return back()->withInput();
    }

    public function destroy(Request $request, $id)
    {
        $myObject = Product::query()->findOrFail($id);

        if (!empty($myObject->id)) {
            Product::destroy($id);
        }

        $request->session()->flash('success', trans('common.delete.success'));

        return redirect(admin_url('woocommerce/products'));
    }

    /**
     * delete multi.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroyMulti(Request $request): RedirectResponse
    {
        $params = $request->all();

        if (!empty($params['ids'])) {
            $items = Product::query()->whereIn('id', $params['ids'])->get();
            foreach ($items as $item) {
                $item->delete();
            }
            $request->session()->flash('success', trans('common.delete.success'));
        } else {
            $request->session()->flash('error', trans('common.error_check_ids'));
        }

        return back();
    }

    /**
     * api product get list
     */
    public function apiProduct(Request $request): LengthAwarePaginator
    {
        $params = $request->only('keyword');

        $object = Product::query();

        if (!empty($params['keyword'])) {
            $object->where('title', 'like', $params['keyword'] . '%');
            $object->orWhere('sku', 'like', $params['keyword'] . '%');
        }

        $column = ['id', 'sku', 'title', 'price', 'price_promotion'];

        return $object->orderBy('id', 'desc')->select($column)->paginate(20);
    }
}
