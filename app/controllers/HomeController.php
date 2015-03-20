<?php
use Illuminate\Support\MessageBag;
class HomeController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Default Home Controller
	|--------------------------------------------------------------------------
	|
	| You may wish to use controllers instead of, or in addition to, Closure
	| based routes. That's great! Here is an example controller method to
	| get you started. To route to this controller, just add the route:
	|
	|	Route::get('/', 'HomeController@showWelcome');
	|
	*/

	public function showWelcome()
	{
		echo '
		<h1> QuizAPP server</h1>
		<a href="'.URL::route('login').'">Login</a>
		';
	}

	public function show_home()
	{
		$quiz = Quiz::where('instructor','=',Auth::user()->id)->get();
		View::share('quizzes',$quiz);
		return View::make('pages.home');
	}

	// Show passcode for a quiz
	public function show_passcode($id)
	{
		$couseid =  explode(":", $id);
		if(sizeof($couseid)!=2) return Error::make(404,404);
		
		// Assume that $id is of form coursecode-quizid 
		$quiz = Quiz::find($couseid[1]);
		if(is_null($quiz)) return Error::make(404,404);

		if(strtoupper($quiz->course_code) != strtoupper($couseid[0])) 
			return Error::make(404,404);
		$codes = json_decode($quiz->key);
		Passcode::printcode($codes);

		echo '<br><br><h3>Passcode : ';
		echo json_encode($codes);
		echo '</h3>';
	}

	// generate passcode
	public function passcode()
	{
		$code = Passcode::genCode();
		$pass = Passcode::genPass($code);
		Passcode::printcode($code);
		Passcode::printcode($pass);
		echo '<br><br><h3>Passcode : ';
		echo json_encode($code);
		echo '</h3>';
	}

	public function show_login()
	{
		return View::make('pages.login');
	}

	public function login()
	{
		$data = Input::all();

		if (Auth::attempt($data))
		    return Redirect::to('/');
		else
		{
		    $message_arr = array('message' => 'Invalid username or password!');
		    return View::make('pages.login', $message_arr);
		}
	}

	public function logout()
	{
		Auth::logout();
		return Redirect::Route('login');
	}

	public function show_add_new()
	{
		return View::make('pages.add_new');
	}

	public function add_new()
	{

		$messageBag = new MessageBag;
		if(!(Input::hasFile('file') && Input::file('file')->isValid())){
			$messageBag->add('message', 'Error in recieveing file');
			return Redirect::back()->with('error',$messageBag) ;
		}

		// Keep the file with random name in cache
		$destinationPath = storage_path().'/cache';
		$fileName = md5(uniqid()).'.md';
		Input::file('file')->move($destinationPath, $fileName);
		

		$lines = array();
		foreach(file($destinationPath.'/'.$fileName) as $line) {
		    if(trim($line)!="")
		    	array_push($lines,trim($line));
		}
		// Delete the file
		unlink($destinationPath.'/'.$fileName);
		$lenfile = sizeof($lines);
		// parsing Quiz
		{
			if($lenfile<7){
				$messageBag->add('message', 'The markup is Invalid');
				return Redirect::back()->with('error',$messageBag) ;
			}

			$quiz = new Quiz;
			$quiz->course_code = $lines[0];
			
			if($lines[1]!="'''")
			{
				$messageBag->add('message', 'Quiz Description not provided');
				return Redirect::back()->with('error',$messageBag) ;
			}
			$desc = '';
			$i = 2;
			while($i<$lenfile && $lines[$i++]!="'''"){
				if($desc!="")
					$desc.="\r\n";
				$desc .= $lines[$i-1];
			}

			if($i==$lenfile){
				$messageBag->add('message', 'Quiz Description not provided in format');
				return Redirect::back()->with('error',$messageBag) ;
			}
			if(intval($lines[$i]) ==0){
				echo intval($lines[$i]);
			}
			$quiz->time = intval($lines[$i++]);
			$quiz->description = $desc;
			
			if($i==$lenfile){
				$messageBag->add('message', 'Tell if quiz is authenticated');
				return Redirect::back()->with('error',$messageBag) ;
			}
			$temp = explode(":",$lines[$i++]);
			if(sizeof($temp)<2){
				$messageBag->add('message', 'Tell if quiz is authenticated');
				return Redirect::back()->with('error',$messageBag) ;
			}
			if(trim($temp[1])=="yes")
				$quiz->skip_auth=0;
			else
				$quiz->skip_auth=1;

			if($i==$lenfile){
				$messageBag->add('message', 'Tell if marks display is required');
				return Redirect::back()->with('error',$messageBag) ;
			}
			$temp = explode(":",$lines[$i++]);
			if(sizeof($temp)<2){
				$messageBag->add('message', 'Tell if marks display is required');
				return Redirect::back()->with('error',$messageBag) ;
			}
			if(trim($temp[1])=="yes")
				$quiz->show_marks=1;
			else
				$quiz->show_marks=0;

			if($i==$lenfile){
				$messageBag->add('message', 'Tell if answer display is required');
				return Redirect::back()->with('error',$messageBag) ;
			}
			$temp = explode(":",$lines[$i++]);
			if(sizeof($temp)<2){
				$messageBag->add('message', 'Tell if answer display is required');
				return Redirect::back()->with('error',$messageBag) ;
			}
			if(trim($temp[1])=="yes")
				$quiz->show_answers=1;
			else
				$quiz->show_answers=0;

			$quiz->keyset = Passcode::genCode();
			$quiz->key = json_encode(Passcode::genPass($quiz->keyset));
			$quiz->keyset = json_encode($quiz->keyset);
			$quiz->instructor = Auth::user()->id;
			$quiz->save();
		}

		
		while($i < $lenfile && !(strpos($lines[$i++],"**********") !==false));

		while($i < $lenfile)
		{
			$ques = array();
			while($i<$lenfile && !(strpos($lines[$i],"**********") !==false)){
				array_push($ques, $lines[$i]);
				$i++;
			}
			
			// Parsing a Question Now
			$breakloop=0;
			while(($breakloop++) == 0)
			{
				$len = sizeof($ques);
				if($len<9)
					break;
				$q = new Question;
				$q->quiz = $quiz->id;
				$j = 0;
				
				$q->question_no = $ques[$j++];
				$q->marks = floatval($ques[$j++]);
				
				if($ques[$j++]!="'''") break;

				$q->question = "";
				while($j<$len && $ques[$j++]!="'''") $q->question.=$ques[$j-1];	
				

				$q->type = intval($ques[$j++]);
				if(!in_array($q->type, array(1,2,3,4,5,6))) break;

				$options = array();
				$answer = array();
				
				if($ques[$j++]!="'''") break;
				$count = "a";
				while($j<$len && $ques[$j]!="'''"){
					if($q->type == 1 || $q->type==2){
						
						$ans = array();
						$ans['id']=$count;
						
						if($ques[$j][0]=="*"){
							array_push($answer, $count);
							$ans['text']=substr($ques[$j], 2);
						}
						else
							$ans['text']=$ques[$j];
						array_push($options, $ans);
						$count++;
					}
					else if($q->type ==4){
						array_push($answer, floatval($ques[$j]));
					}
					else if($q->type ==3){
						array_push($answer, intval($ques[$j]));
					}
					else if($q->type ==5){
						array_push($answer, $ques[$j]);
					}
					$j++;
				}
				if($q->type == 4 && sizeof($answer) < 2)
					break;
				
				$q->options = json_encode($options);
				$q->answer = json_encode($answer);
				$q->save();

			}
			$i++;
		}
		
		return Question::where('quiz','=',$quiz->id)->get();
	}

	public function delete_quiz($id)
	{
		$quiz = Quiz::find($id);
		if(is_null($quiz))
			return Redirect::back();
		if(Auth::user()->id != $quiz->instructor)
			return Redirect::back();

		$quiz->delete($id);
		return Redirect::back();
	}	
}
