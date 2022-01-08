<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::auth();

Route::get('/home', 'HomeController@index');

Route::get('/about', 'TestController@index');

Route::get('/photo', 'PhotoController@index');
Route::post('/upload', 'PhotoController@upload');
Route::get('/show', 'PhotoController@show');



Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
