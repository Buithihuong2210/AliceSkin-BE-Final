<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    UserController,
    SocialController,
    BrandController,
    ProductController,
    SocialAuthController,
    ImageController,
    PasswordResetController,
    CartController,
    BlogController,
    HashtagController,
    CommentController,
    SurveyController,
    QuestionController,
    ResponseController,
    ShippingController,
    OrderController,
    VoucherController,
    ReviewController,
    VNPayController
};


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('users', [UserController::class, 'index']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->put('/user/update/{id}', [UserController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/user/{id}', [UserController::class, 'destroy']);



Route::post('password/forgot', [PasswordResetController::class, 'sendResetLink']);
Route::post('password/reset', [PasswordResetController::class, 'reset']);
Route::get('auth/google', [SocialController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [SocialController::class, 'handleGoogleCallback']);

Route::group(['middleware' => 'web'], function () {
    Route::get('auth/facebook', [SocialAuthController::class, 'redirectToFacebook']);
    Route::get('auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);
});

Route::prefix('upload')->group(function () {
    Route::post('/', [ImageController::class, 'uploadImage']);
});

Route::get('/orders/{order_id}/items', [OrderController::class, 'getOrderItems']);
Route::get('/products/{product_id}/reviews', [ProductController::class, 'getReviewsByProduct']);


Route::get('/payment/vnpay/return', [VNPayController::class, 'handlePaymentReturn']);


Route::get('/payments/total', [VNPayController::class, 'getTotalPayments']);

Route::get('/orders/user/{userId}', [OrderController::class, 'viewAllOrdersByUserId']);
Route::get('/order/{orderId}', [OrderController::class, 'getOrderById']);


Route::get('/blogs/draft', [BlogController::class, 'listDraftBlogs']);
Route::get('/blogs/published', [BlogController::class, 'showAllPublishedBlogs']);

Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);
    Route::get('/{id}', [BrandController::class, 'show']);
});

Route::get('/orders/total-payments', [OrderController::class, 'getTotalPaymentsForBothMethods']);


Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);
    Route::get('/{id}', [BrandController::class, 'show']);
});

Route::prefix('shippings')->group(function () {
    Route::get('/', [ShippingController::class, 'index']);
    Route::get('/{shipping_id}', [ShippingController::class, 'show']);
});

Route::prefix('vouchers')->group(function () {
    Route::get('/', [VoucherController::class, 'index']);
    Route::get('/{voucher_id}', [VoucherController::class, 'show']);
});


Route::get('brands/products/{brandId}', [BrandController::class, 'getProductsByBrand']);

Route::prefix('hashtags')->group(function () {
    Route::get('/', [HashtagController::class, 'index']);
    Route::post('/', [HashtagController::class, 'store']);
    Route::get('/search', [HashtagController::class, 'search']);
});



// User routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/blogs/like/{blog_id}', [BlogController::class, 'likeBlog']);
    Route::delete('/blogs/like/{blog_id}', [BlogController::class, 'unlikeBlog']);


    Route::get('/my-blogs', [BlogController::class, 'showUserBlogs']);

    Route::post('/change-password', [UserController::class, 'changePassword']);

    // Survey routes for users
    Route::prefix('surveys')->group(function () {
            Route::post('/{survey_id}/responses', [ResponseController::class, 'store']);
        Route::put('/{survey_id}/responses', [ResponseController::class, 'update']);
    });

    Route::prefix('responses')->group(function () {
        Route::get('/my', [ResponseController::class, 'showResponse']);
        Route::get('/recommend', [ResponseController::class, 'recommendItem']);
    });

    Route::prefix('payment')->group(function () {
        Route::post('/vnpay/create/{order_id}', [VNPayController::class, 'createPayment']);
    });


    // User routes for Cart, Products, Blogs
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::get('/{id}', [CartController::class, 'show']);
        Route::put('/{item}', [CartController::class, 'update']);
        Route::delete('/{item}', [CartController::class, 'destroy']);
        Route::post('/complete', [CartController::class, 'completeCart']);

    });

// Order routes
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/' ,[OrderController::class, 'showAll']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product_id}', [ProductController::class, 'show']);
    });

    Route::prefix('reviews')->group(function () {
        Route::post('/{order_id}', [ReviewController::class, 'store']);
        Route::put('/{order_id}/{review_id}', [ReviewController::class, 'update']);
        Route::delete('/{order_id}/{review_id}', [ReviewController::class, 'destroy']);
        Route::get('/product/{product_id}/count', [ReviewController::class, 'countReviewsByProduct']);
        Route::get('/user', [ReviewController::class, 'getUserReviews']);

    });

    Route::prefix('blogs')->group(function () {
        Route::post('/', [BlogController::class, 'store']);
        Route::get('/', [BlogController::class, 'showAll']);
        Route::put('/{blog}', [BlogController::class, 'updateUser']);
        Route::get('/{blog}', [BlogController::class, 'show']);
        Route::delete('/delete/{blog_id}', [BlogController::class, 'destroy']);
    });

    Route::prefix('blogs/{blog_id}')->group(function () {
        Route::get('/comments', [CommentController::class, 'index']);
        Route::post('/comments', [CommentController::class, 'store']);
        Route::put('/comments/{comment_id}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment_id}', [CommentController::class, 'destroy']);
    });

    Route::prefix('hashtags')->group(function () {
        Route::get('/', [HashtagController::class, 'index']);
    Route::get('/{hashtag_id}', [HashtagController::class, 'show']);
        Route::get('/by-id/{hashtag_id}', [HashtagController::class, 'getByID']);
    });
});

Route::prefix('surveys/{survey_id}/questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']);
    Route::get('/{question_id}', [QuestionController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('manager')->group(function () {

    // user management
    Route::get('/users', [UserController::class, 'index']);
    Route::put('/user/update/{id}', [UserController::class, 'update']);
    Route::delete('/user/{id}', [UserController::class, 'destroy']);
    Route::put('/user/{userId}/role', [UserController::class, 'updateRole']);
    Route::get('/users/{id}', [UserController::class, 'getUserById']);


    // managing brands and products
    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']);
        Route::post('/', [BrandController::class, 'store']);
        Route::get('/{id}', [BrandController::class, 'show']);
        Route::put('/{id}', [BrandController::class, 'update']);
        Route::delete('/{id}', [BrandController::class, 'destroy']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{product_id}', [ProductController::class, 'show']);
        Route::put('/{product_id}', [ProductController::class, 'update']);
        Route::delete('/{product_id}', [ProductController::class, 'destroy']);
        Route::put('/{product_id}/status', [ProductController::class, 'changeStatus']);
    });

    // Admin hashtag management routes
    Route::prefix('hashtags')->group(function () {
        Route::get('/', [HashtagController::class, 'index']);
        Route::post('/', [HashtagController::class, 'store']);
        Route::get('/{hashtag_id}', [HashtagController::class, 'show']);
        Route::put('/{hashtag_id}', [HashtagController::class, 'update']);
        Route::delete('/{hashtag_id}', [HashtagController::class, 'destroy']);
        Route::get('/by-id/{hashtag_id}', [HashtagController::class, 'getByID']);
    });

    Route::put('/update-status/{order_id}' ,[OrderController::class, 'updateOrderStatus']);
    Route::get('/orders/canceled', [OrderController::class, 'getCanceledOrders']);



    Route::prefix('shippings')->group(function () {
        Route::get('/', [ShippingController::class, 'index']);
        Route::get('/{shipping_id}', [ShippingController::class, 'show']);
        Route::post('/', [ShippingController::class, 'store']);
        Route::put('/{shipping_id}', [ShippingController::class, 'update']);
        Route::delete('/{shipping_id}', [ShippingController::class, 'destroy']);
    });

    // Admin routes for managing vouchers
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index']);
        Route::post('/', [VoucherController::class, 'store']);
        Route::get('/{voucher_id}', [VoucherController::class, 'show']);
        Route::put('/{voucher_id}', [VoucherController::class, 'update']);
        Route::delete('/{voucher_id}', [VoucherController::class, 'destroy']);
        // Route to change voucher status
        Route::put('/{voucher_id}/status', [VoucherController::class, 'changeStatus']);
    });

    Route::post('/orders/confirm-delivery/{order_id}', [OrderController::class, 'confirmDelivery']);

    Route::prefix('reviews')->group(function () {
        Route::get('/product/{product_id}/count', [ReviewController::class, 'countReviewsByProduct']);
    });

    Route::prefix('surveys')->group(function () {
        Route::post('/', [SurveyController::class, 'store']);
        Route::get('/', [SurveyController::class, 'index']);
        Route::get('/{survey_id}', [SurveyController::class, 'show']);
        Route::put('/{survey_id}', [SurveyController::class, 'update']);
        Route::delete('/{survey_id}', [SurveyController::class, 'destroy']);
    });

    // Question management routes
    Route::prefix('surveys/{survey_id}/questions')->group(function () {
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/', [QuestionController::class, 'index']);
        Route::get('/{question_id}', [QuestionController::class, 'show']);
        Route::put('/{question_id}', [QuestionController::class, 'update']);
        Route::delete('/{question_id}', [QuestionController::class, 'destroy']);
    });

    // Response management routes ()
    Route::prefix('responses')->group(function () {
        Route::get('/', [ResponseController::class, 'index']);
        Route::get('/{response_id}', [ResponseController::class, 'show']);
        Route::delete('/{response_id}', [ResponseController::class, 'destroy']);
    });

    Route::prefix('blogs')->group(function () {
        Route::post('/', [BlogController::class, 'store']);
        Route::put('/{blog_id}', [BlogController::class, 'updateAdmin']);
        Route::put('/changestatus/{blog_id}', [BlogController::class, 'changeStatus']);
        Route::get('/{blog}', [BlogController::class, 'show']);
    });

    Route::prefix('blogs')->group(function () {
        Route::delete('/{blog_id}', [BlogController::class, 'destroy']);
        Route::put('/{blog_id}/likes', [BlogController::class, 'setLikes']);
    });

    Route::get('/payments', [VNPayController::class, 'getAllPayments']);

});

//// Routes cho staff
Route::middleware(['auth:sanctum', 'role:staff'])->prefix('manager')->group(function () {
    Route::prefix('surveys')->group(function () {
        Route::post('/', [SurveyController::class, 'store']); // Create a new survey
        Route::get('/', [SurveyController::class, 'index']); // List all surveys
        Route::get('/{survey_id}', [SurveyController::class, 'show']); // Show a specific survey
        Route::put('/{survey_id}', [SurveyController::class, 'update']); // Update a specific survey
    });

    // Question management routes
    Route::prefix('surveys/{survey_id}/questions')->group(function () {
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/', [QuestionController::class, 'index']);
        Route::get('/{question_id}', [QuestionController::class, 'show']);
        Route::put('/{question_id}', [QuestionController::class, 'update']);
    });

    // Response management routes ()
    Route::prefix('responses')->group(function () {
        Route::get('/', [ResponseController::class, 'index']);
        Route::get('/{response_id}', [ResponseController::class, 'show']);
    });

    Route::prefix('blogs')->group(function () {
        Route::post('/', [BlogController::class, 'store']);
        Route::put('/{blog_id}', [BlogController::class, 'updateAdmin']);
        Route::put('/changestatus/{blog_id}', [BlogController::class, 'changeStatus']);
        Route::get('/{blog}', [BlogController::class, 'show']);
    });

    Route::post('/orders/confirm-delivery/{order_id}', [OrderController::class, 'confirmDelivery']);

});

