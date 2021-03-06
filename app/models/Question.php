<?php
/**
*	This file Question.php contains the Logs Question which is the ORM for table 'Questions'
*	@author Prateek Chandan <prateekchandan5545@gmail.com>
*/

/**
* The Question Class is the ORM for table 'Questions'
* ORM Means Object Relational Model
*/
class Question extends Eloquent {


	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Questions';

	protected $hidden = array('created_at' , 'updated_at' , 'answer' , 'quiz');


}
