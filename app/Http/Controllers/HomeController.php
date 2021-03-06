<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Apartment;
use App\Message;
use App\Sponsored;
use App\View;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ApartmentRequest;
use Braintree_Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */


     public function dashboard() {

       $userId = Auth::user()->id;
       $now = new Carbon();

       $apartments = Apartment::where('user_id', $userId)->get();
       return view('page.dashboard', compact('apartments', 'sponsoredIDs', 'now'));
     }

     public function createApartment() {

       return view('page.create-apartment');
     }

     public function storeApartment(ApartmentRequest $request) {

       $validatedData = $request->validated();

       $image = $request->file('img_path');
       $ext = $image->getClientOriginalExtension();
       $filename = $image->getFilename().'.'.$ext;
       $image->storeAs('public/images',$filename);

       $apartment = Apartment::make($validatedData);
       $apartment->img_path = $filename;
       $user = Auth::user();
       $apartment->user()->associate($user)->save();

       return redirect('dashboard')->with('success','Appartamento aggiunto con successo!');
     }

     public function editApartment($id) {

       $apartment = Apartment::findOrFail($id);

       return view('page.edit-apartment', compact('apartment'));
     }

     public function updateApartment(ApartmentRequest $request, $id) {

       $validatedData = $request->validated();

       $image = $request->file('img_path');
       $ext = $image->getClientOriginalExtension();
       $filename = $image->getFilename().'.'.$ext;
       $image->storeAs('public/images',$filename);

       $apartment = Apartment::findOrFail($id);
       $apartment->update($validatedData);
       $apartment->img_path = $filename;
       $apartment->save();

       return redirect('dashboard')->with('success','Appartamento modificato con successo!');
     }

     public function deleteApartment($id) {

       $apartment = Apartment::findORFail($id)->delete();

       return redirect('dashboard')->with('success','Appartamento eliminato con successo!');
     }

     public function receivedMessages() {

       $user = Auth::user();

       $messages = Message::select('apartments.description', 'messages.name', 'messages.lastname', 'messages.email', 'messages.title', 'messages.content', 'messages.id')->join('apartments' , 'messages.apartment_id', '=', 'apartments.id')->where('messages.user_id', $user->id)->get();

       return view('page.received-messages', compact('messages'));
     }

     public function deleteMessages($id) {

       Message::findORFail($id)->delete();

       return redirect()->back()->with('success', 'Messaggio eliminato con successo!');
     }

     public function sponsorizeApartment($id) {

       return view('page.sponsorize-apartment', compact('id'));
     }

     public function paymentProcess(Request $request) {

       $payload = $request->input('payload', false);
       $nonce = $payload['nonce'];

       $status = Braintree_Transaction::sale([
	        'amount' => '10.00',
	        'paymentMethodNonce' => $nonce,
	        'options' => [
	          'submitForSettlement' => True
	         ]
        ]);

        return response()->json($status);
     }

     public function paymentSuccess(Request $request) {

      $hours = $request->hours;
      $id = $request->id;
      $apartment = Apartment::findOrFail($id);
      $now = new Carbon();

      if ($hours == 24) {

        $title = "Un giorno - 8 euro";
        $price = 8;
        $end_sponsored = $now->add(1, 'day');
      } elseif ($hours == 168) {

        $title = "Una settimana - 40 euro";
        $price = 40;
        $end_sponsored = $now->add(7, 'day');
      } elseif ($hours == 672) {

        $title = "Un mese - 150 euro";
        $price = 150;
        $end_sponsored = $now->add(30, 'day');
      }

       $sponsored = new Sponsored;
       $sponsored->title = $title;
       $sponsored->price = $price;
       $sponsored->end_sponsored = $end_sponsored;
       $sponsored->save();
       $sponsored->apartments()->attach($apartment);

       return response()->json('sponsorizzazione avvenuta con successo!');
     }

     public function showStats($id) {

       $totalMessages = Message::where('apartment_id', $id)->count();
       $totalViews = View::where('apartment_id', $id)->count();

       $dates = View::where('apartment_id', $id)
                     ->select('created_at')->get()->all();

       $months = [];

       foreach ($dates as $date) {

         $months[] = $date['created_at']->englishMonth;
       }

       return view('page.show-stats', compact('totalViews', 'totalMessages', 'months'));
     }
}
