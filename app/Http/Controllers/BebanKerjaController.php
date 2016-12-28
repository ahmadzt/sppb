<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;
use DateTime;
use Validator;
use App\Http\Requests;
use App\Record;
use App\User;
use App\Label;
use Carbon\Carbon;
use View;
use File;
use Zipper;
use Auth;

/*
 *	Need to be done:
 *	1. Validate file size before upload
 *	2. Check if file already exist before upload
 *  4. Check if file is succesfully deleted
 */

class BebanKerjaController extends Controller
{
    protected $label_name;

    protected $label;

    function __construct(){
		$this->middleware('auth');

        $this->label_name = 'BEBANKERJA';

        $this->label = Label::where('name', '=', $this->label_name)->first();
	}

    public function index(){

        $user = Auth::user()->name;

        if ($user == 'elizabeth') {
            
            // get all files from all users
            // label_id = 1 for bebankerja
            $records = Record::with('user.hospital')->with('label')->where('label_id', '=', $this->label->id)->get();
        
        } else {

            // get all files associated with the user
            $records = Record::with('user.hospital')->where('user_id', '=', Auth::user()->id)->with('label')->where('label_id', '=', $this->label->id)->get();
        }
    	
    	foreach ($records as $record) {
            $dateM = date("m", strtotime($record->created_at));
    		$dateObj   = DateTime::createFromFormat('!m', $dateM);
			$record->month = $dateObj->format('F'); // March

            $dateY = date("Y", strtotime($record->created_at));
            $record->year = $dateY;
        }
    	
    	// $records = Record::with('user.hospital')->with('label')->get();
        // $message = 'test';

    	return View::make('bebankerja.index', compact('records'));
    }

    public function store(Request $request){
         
        // validate file is exist & in xls format
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx,xlsm',
            'uploader' => 'required'
            ]);

        // get uploaded file info
        $file = $request->file('file');

        $filemime = $file->getMimeType();

        // get month in string format
        $short_month = $this->getMonthShortName(Carbon::now()->month);
        $full_month = $this->getMonthFullName(Carbon::now()->month);

        // get year in 2 digit format
        $year = $this->getYearShort(Carbon::now()->year);

        // retrieve user info
        $user = User::where('id', '=', Auth::user()->id)->with('hospital')->first();

        // assign new file name
        $filename = $user->hospital->short_name.'_bebankerja_'.$short_month.$year.'.'.$file->getClientOriginalExtension();

        // Move Uploaded File
        $destinationPath = 'records/bebankerja/'.Carbon::now()->year.'/'.$full_month;

        $file->move($destinationPath, $filename);

        // save file's information to files table on db
        $files = Record::create([
            'name' => $filename,
            'mime' => $filemime,
            'uploader' => $request->uploader,
            'user_id' => Auth::user()->id,
            'label_id' => $this->label->id
            ]);

        return redirect('bebankerja');

    	/*
		 *	filename : $file->getClientOriginalName()
		 *	extension : $file->getClientOriginalExtension()
		 *	path : $file->getRealPath()
		 *	size : $file->getSize()
		 *	mime : $file->getMimeType()
    	 */ 
    }

    // download file from storage
    public function get($id){

    	$file = Record::where('id', '=', $id)->firstOrFail();

        $month = $this->getMonthFullName($file->created_at->format('m'));

        $year = $file->created_at->format('Y');

    	// PDF file is stored under sppb/records/bebankerja
    	$path = public_path(). "/records/bebankerja/".$year.'/'.$month.'/'.$file->name;

    	$headers = array('Content-Type' => $file->mime);

    	// check if file exist
    	if(File::exists($path)){
    		
    		// return the actual file to the user
    		return response()->download($path, $file->name, $headers);
    	
    	} else{
    		
    		dd('File is not exists.');
    	
    	}
		
    }

    // delete file records on storage and database
    public function delete($id){
            

        $file = Record::where('id', '=', $id)->firstOrFail();

        $month = $this->getMonthFullName($file->created_at->format('m'));

        $year = $file->created_at->format('Y');

        // PDF file is stored under sppb/records/bebankerja
        $path = public_path(). "/records/bebankerja/".$year.'/'.$month.'/'.$file->name;

        File::Delete($path);

        // check if file exist
        if(File::exists($path)){
            
            dd('File is still exists.');
        
        } else{
            
            Record::destroy($id);
        
        }

    	return redirect('bebankerja'); 
    }

    // this function return zip file to the user
    public function getCollection(Request $request){

        // path to folder based on selected year
        $path = public_path(). "/records/bebankerja/".$request->year;

        // check if the directory exist
        if (File::isDirectory($path)) {

            $file_extension = '.zip';

            // assign a new name for the zip file
            $filename = 'bebankerja_'.$request->year.$file_extension;

            // path to the new zip file
            $zipPath = public_path()."/records/bebankerja/collection/".$filename;

            // create zip file
            Zipper::make($zipPath)->add($path);

            if (File::exists($zipPath)) {

                // return file to the user
                return response()->download($zipPath);
            }

            return redirect('bebankerja'); 

        } else {

            $records = Record::with('user.hospital')->with('label')->where('label_id', '=', $this->label->id)->get();

            $messages = 'Tiada rekod pada tahun '.$request->year;

            \Session::flash('error_message', $messages);

            return redirect('bebankerja'); 

        }
        
    }

    // this function recieve month in integer and return the month's full name in string
    public function getMonthFullName($month){
    	return date("F", mktime(0, 0, 0, $month, 1));
    }

    // this function recieve month in integer and return the month's short name in string
    public function getMonthShortName($month){
        return date("M", mktime(0, 0, 0, $month, 1));
    }

    // this function recieve year in integer and return in 2 digit year representation
    public function getYearShort($full_year){
        $year = DateTime::createFromFormat('Y', $full_year);
        return $year->format('y');
    }

}