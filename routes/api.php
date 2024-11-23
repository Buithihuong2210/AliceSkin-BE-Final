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
    Route::get('/', [BrandController::class, 'index']); // List all brands
    Route::get('/{id}', [BrandController::class, 'show']); // Show a specific brand
});

Route::get('/orders/total-payments', [OrderController::class, 'getTotalPaymentsForBothMethods']);


Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']); // List all brands
    Route::get('/{id}', [BrandController::class, 'show']); // Show a specific brand
});

Route::prefix('shippings')->group(function () {
    Route::get('/', [ShippingController::class, 'index']); // List all shipping records
    Route::get('/{shipping_id}', [ShippingController::class, 'show']); // Get a specific shipping record
});

Route::prefix('vouchers')->group(function () {
    Route::get('/', [VoucherController::class, 'index']); // List all vouchers
    Route::get('/{voucher_id}', [VoucherController::class, 'show']); // Show a specific voucher
});


Route::get('brands/products/{brandId}', [BrandController::class, 'getProductsByBrand']);

//Route::get('brands/products/{brandId}', [BrandController::class, 'getProductsByBrand'])->middleware('can:view products of a brand');

Route::prefix('hashtags')->group(function () {
    Route::get('/', [HashtagController::class, 'index']); // List all hashtags
    Route::post('/', [HashtagController::class, 'store']); // Store a new hashtag
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


    // User routes for Cart, Products, Blogs, etc.
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);       // List all items in cart
        Route::post('/', [CartController::class, 'store']);      // Add item to cart
        Route::get('/{id}', [CartController::class, 'show']);    // Show specific cart
        Route::put('/{item}', [CartController::class, 'update']); // Update specific cart item
        Route::delete('/{item}', [CartController::class, 'destroy']); // Delete specific cart item
        Route::post('/complete', [CartController::class, 'completeCart']);

    });

// Order routes
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);  // Create a new order
        Route::get('/' ,[OrderController::class, 'showAll']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']); // List all products
        Route::get('/{product_id}', [ProductController::class, 'show']); // Show a specific product
    });

    Route::prefix('reviews')->group(function () {
        Route::post('/{order_id}', [ReviewController::class, 'store']);
        Route::put('/{order_id}/{review_id}', [ReviewController::class, 'update']); // Update a review
        Route::delete('/{order_id}/{review_id}', [ReviewController::class, 'destroy']); // Delete a specific review
        Route::get('/product/{product_id}/count', [ReviewController::class, 'countReviewsByProduct']); // Count reviews for a product
        Route::get('/user', [ReviewController::class, 'getUserReviews']); // Count reviews for a product

    });

    Route::prefix('blogs')->group(function () {
        Route::post('/', [BlogController::class, 'store']);
        Route::get('/', [BlogController::class, 'showAll']);
        Route::put('/{blog}', [BlogController::class, 'updateUser']);
        Route::get('/{blog}', [BlogController::class, 'show']);
        Route::delete('/delete/{blog_id}', [BlogController::class, 'destroy']);
    });

    Route::prefix('blogs/{blog_id}')->group(function () {
        // Get all comments for a specific blog
        Route::get('/comments', [CommentController::class, 'index']);
        // Store a new comment or reply to a comment
        Route::post('/comments', [CommentController::class, 'store']);
        // Update a specific comment
        Route::put('/comments/{comment_id}', [CommentController::class, 'update']);
        // Delete a specific comment
        Route::delete('/comments/{comment_id}', [CommentController::class, 'destroy']);
    });

    Route::prefix('hashtags')->group(function () {
        Route::get('/', [HashtagController::class, 'index']); // List all hashtags
    Route::get('/{hashtag_id}', [HashtagController::class, 'show']); // Show a specific hashtag
        Route::get('/by-id/{hashtag_id}', [HashtagController::class, 'getByID']); // Get hashtag by ID
    });
});

Route::prefix('surveys/{survey_id}/questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']); // List questions for a specific survey
    Route::get('/{question_id}', [QuestionController::class, 'show']); // Show a specific question
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('manager')->group(function () {

    // Admin routes for user management
    Route::get('/users', [UserController::class, 'index']);
    Route::put('/user/update/{id}', [UserController::class, 'update']);
    Route::delete('/user/{id}', [UserController::class, 'destroy']);
    Route::put('/user/{userId}/role', [UserController::class, 'updateRole']);
    Route::get('/users/{id}', [UserController::class, 'getUserById']);


    // Admin routes for managing brands and products
    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']); // List all brands
        Route::post('/', [BrandController::class, 'store']); // Store a new brand
        Route::get('/{id}', [BrandController::class, 'show']); // Show a specific brand
        Route::put('/{id}', [BrandController::class, 'update']); // Update a specific brand
        Route::delete('/{id}', [BrandController::class, 'destroy']); // Delete a specific brand
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']); // List all products
        Route::post('/', [ProductController::class, 'store']); // Store a new product
        Route::get('/{product_id}', [ProductController::class, 'show']); // Show a specific product
        Route::put('/{product_id}', [ProductController::class, 'update']); // Update a specific product
        Route::delete('/{product_id}', [ProductController::class, 'destroy']); // Delete a specific product
        Route::put('/{product_id}/status', [ProductController::class, 'changeStatus']); // Change product status
    });

    // Admin hashtag management routes
    Route::prefix('hashtags')->group(function () {
        Route::get('/', [HashtagController::class, 'index']); // List all hashtags
        Route::post('/', [HashtagController::class, 'store']); // Store a new hashtag
        Route::get('/{hashtag_id}', [HashtagController::class, 'show']); // Show a specific hashtag
        Route::put('/{hashtag_id}', [HashtagController::class, 'update']); // Update a specific hashtag
        Route::delete('/{hashtag_id}', [HashtagController::class, 'destroy']); // Delete a specific hashtag
        Route::get('/by-id/{hashtag_id}', [HashtagController::class, 'getByID']); // Get hashtag by ID
    });

    Route::put('/update-status/{order_id}' ,[OrderController::class, 'updateOrderStatus']);
    Route::get('/orders/canceled', [OrderController::class, 'getCanceledOrders']);



    Route::prefix('shippings')->group(function () {
        Route::get('/', [ShippingController::class, 'index']); // List all shipping records
        Route::get('/{shipping_id}', [ShippingController::class, 'show']); // Get a specific shipping record
        Route::post('/', [ShippingController::class, 'store']); // Create a new shipping record
        Route::put('/{shipping_id}', [ShippingController::class, 'update']); // Update a shipping record
        Route::delete('/{shipping_id}', [ShippingController::class, 'destroy']); // Delete a specific shipping record
    });

    // Admin routes for managing vouchers
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index']); // List all vouchers
        Route::post('/', [VoucherController::class, 'store']); // Create a new voucher
        Route::get('/{voucher_id}', [VoucherController::class, 'show']); // Show a specific voucher
        Route::put('/{voucher_id}', [VoucherController::class, 'update']); // Update a specific voucher
        Route::delete('/{voucher_id}', [VoucherController::class, 'destroy']); // Delete a specific voucher
        // Route to change voucher status
        Route::put('/{voucher_id}/status', [VoucherController::class, 'changeStatus']); // Change status of voucher
    });

    Route::post('/orders/confirm-delivery/{order_id}', [OrderController::class, 'confirmDelivery']);

    Route::prefix('reviews')->group(function () {
        Route::get('/product/{product_id}/count', [ReviewController::class, 'countReviewsByProduct']); // Count reviews for a product
    });

    Route::prefix('surveys')->group(function () {
        Route::post('/', [SurveyController::class, 'store']); // Create a new survey
        Route::get('/', [SurveyController::class, 'index']); // List all surveys
        Route::get('/{survey_id}', [SurveyController::class, 'show']); // Show a specific survey
        Route::put('/{survey_id}', [SurveyController::class, 'update']); // Update a specific survey
        Route::delete('/{survey_id}', [SurveyController::class, 'destroy']); // Delete a specific survey
    });

    // Question management routes
    Route::prefix('surveys/{survey_id}/questions')->group(function () {
        Route::post('/', [QuestionController::class, 'store']); // Add a question to a specific survey
        Route::get('/', [QuestionController::class, 'index']); // List questions for a specific survey
        Route::get('/{question_id}', [QuestionController::class, 'show']); // Show a specific question
        Route::put('/{question_id}', [QuestionController::class, 'update']); // Update a specific question
        Route::delete('/{question_id}', [QuestionController::class, 'destroy']); // Delete a specific question
    });

    // Response management routes (optional, if admins need to see all responses)
    Route::prefix('responses')->group(function () {
        Route::get('/', [ResponseController::class, 'index']); // List all responses
        Route::get('/{response_id}', [ResponseController::class, 'show']); // Show a specific response
        Route::delete('/{response_id}', [ResponseController::class, 'destroy']); // Delete a specific response
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
//Route::middleware(['auth:sanctum', 'role:staff'])->prefix('manager')->group(function () {
//    Route::prefix('surveys')->group(function () {
//        Route::post('/', [SurveyController::class, 'store']); // Create a new survey
//        Route::get('/', [SurveyController::class, 'index']); // List all surveys
//        Route::get('/{survey_id}', [SurveyController::class, 'show']); // Show a specific survey
//        Route::put('/{survey_id}', [SurveyController::class, 'update']); // Update a specific survey
//        Route::delete('/{survey_id}', [SurveyController::class, 'destroy']); // Delete a specific survey
//    });
//
//    // Question management routes
//    Route::prefix('surveys/{survey_id}/questions')->group(function () {
//        Route::post('/', [QuestionController::class, 'store']); // Add a question to a specific survey
//        Route::get('/', [QuestionController::class, 'index']); // List questions for a specific survey
//        Route::get('/{question_id}', [QuestionController::class, 'show']); // Show a specific question
//        Route::put('/{question_id}', [QuestionController::class, 'update']); // Update a specific question
//        Route::delete('/{question_id}', [QuestionController::class, 'destroy']); // Delete a specific question
//    });
//
//    // Response management routes (optional, if admins need to see all responses)
//    Route::prefix('responses')->group(function () {
//        Route::get('/', [ResponseController::class, 'index']); // List all responses
//        Route::get('/{response_id}', [ResponseController::class, 'show']); // Show a specific response
//        Route::delete('/{response_id}', [ResponseController::class, 'destroy']); // Delete a specific response
//    });
//
//    Route::prefix('blogs')->group(function () {
//        Route::post('/', [BlogController::class, 'store']);
//        Route::put('/{blog_id}', [BlogController::class, 'updateAdmin']);
//        Route::put('/changestatus/{blog_id}', [BlogController::class, 'changeStatus']);
//        Route::get('/{blog}', [BlogController::class, 'show']);
//    });
//
//    Route::post('/orders/confirm-delivery/{order_id}', [OrderController::class, 'confirmDelivery']);
//
//});

