<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


//use App\Http\Requests;

class PhotoController extends Controller
{
   public function index()
   {
       return view('photos.upload');
   }

   public function upload(request $request)
   {
        if($request->hasFile('photo')){
            $request->file("photo");
            return  $request->photo->storeAs('storage/public', 'bitfumes.jpg');
            
        } else {
            return "Veuillez choisir une image.";
        }
        
   }

     public function show()
   {
       $url = Storage::url('bitfumes.jpg');
      // dd($url);

       return "<img src='".$url."' />";
        
   }

 }


