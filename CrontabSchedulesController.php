<?php
include_once(__DIR__.'/../helpers/simple_html_dom.php');
class CrontabSchedulesController extends BaseController {

	//send mail background
	public function getSendMailBackground() {

		$start = round(microtime(true) * 1000);

		$data = CrontabSchedules::getDataScheduleEmail();

		foreach ($data as $value) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			if ($value->type == "mail") {
				$value->delete();

				if ($value->type_user == "shareFacebook") {
					$value->delete();
					User::sendShareFacebookEmail($value->user_id, $value->job_id);
				} elseif ($value->type_user == "applyCompany") {
					$value->delete();
					MailHelper::SendApply_MessageEmailForCompany($value->job_id, 'emails.company_apply_content', '[JOB Forward] New application for ');
				} elseif ($value->type_user == "applyUser") {
					$value->delete();
					User::sendApplyEmail($value->user_id, $value->job_id);
				} elseif ($value->type_user == "newStratupPage") {
					MailHelper::Send('emails.new_startup_page', array('company_name' => $value->name, 'company_token' => $value->messages) , $value->email, 'Job Forward: Startup Essence links');
				}

			} elseif ($value->type == "mailshare") {
				$value->delete();

				$job=Jobs::jobsDetail($value->job_id);

				if (($value->type_user == "employee") && !empty($value->email)) {
					MailHelper::Send('emails.share_job_employee', array('job'=>$job), $value->email, 'Your company, ' . $job['cName'] . ', starts a new recruiting');
				} elseif (($value->type_user == "endorser") && !empty($value->email)) {
					MailHelper::Send('emails.recruiting_new_job', array('company_name'=>$job['cName'], 'job'=>$job), $value->email, 'New recruiment from ' . $job['cName']);
				} elseif (($value->type_user == "a") && !empty($value->email)) {

					$friends = User::getUserFriendIsEmployee($value->user_id, $value->link);

					$nameFriends = '';
					foreach ($friends as $friend) { 
						$nameFriends .= $friend->name . ', ';
					}
					$nameFriends = substr($nameFriends, 0, strlen($nameFriends) - 2);

					MailHelper::Send('emails.share_job_usera', array('job'=>$job, 'friends'=>$friends, 'nameFriends'=>$nameFriends), $value->email, $nameFriends . " 's company starts a new recruiting");

				} elseif (($value->type_user == "b") && !empty($value->email)) {

					$friends = User::getUserFriendIsEndorser($value->user_id, $value->link);

					$nameFriends = '';
					foreach ($friends as $friend) { 
						$nameFriends .= $friend->name . ', ';
					}
					$nameFriends = substr($nameFriends, 0, strlen($nameFriends) - 2);

					MailHelper::Send('emails.share_job_userb', array('job'=>$job, 'friends'=>$friends, 'nameFriends'=>$nameFriends), $value->email, $job['cName'] . ' starts a new recruiting');

				} elseif (($value->type_user == "c") && !empty($value->email)) {

					$friendsEmployee = User::getUserFriendIsEmployee($value->user_id, $value->link);

					$nameFriendsEmployee = '';
					foreach ($friendsEmployee as $friendEmp) { 
						$nameFriendsEmployee .= $friendEmp->name . ', ';
					}
					if ($nameFriendsEmployee != '') {
						$nameFriendsEmployee = substr($nameFriendsEmployee, 0, strlen($nameFriendsEmployee) - 2);
					}

					$friendsEndorser = User::getUserFriendIsEndorser($value->user_id, $value->link);

					$nameFriendsEndorser = '';
					foreach ($friendsEndorser as $friendEnd) { 
						$nameFriendsEndorser .= $friendEnd->name . ', ';
					}
					if ($nameFriendsEndorser != '') {
						$nameFriendsEndorser = substr($nameFriendsEndorser, 0, strlen($nameFriendsEndorser) - 2);
					}

					$friends = $friendsEmployee;
					foreach ($friendsEndorser as $endorser) {
						if (!in_array($endorser, $friends)) {
							array_push($friends, $endorser);
						}
					}

					MailHelper::Send('emails.share_job_userc', array('job'=>$job, 'friends'=>$friends, 'nameFriendsEmployee'=>$nameFriendsEmployee, 'nameFriendsEndorser'=>$nameFriendsEndorser), $value->email, 'New job post from ' . $job['cName'] . ', let support it now!');

				}
			} elseif ($value->type == "message") {   
			    $value->delete();     
                try{
                    if ($value->type_user) {
                        MailHelper::SendApply_MessageEmailForCompany($value->job_id,'emails.company_message_content',$value->messages);
                    } else {
                        MailHelper::Send('MessagesChat.user_message_email_content',array('username'=>$value->name),$value->email,$value->messages);
                    }
                } catch (Exception $e) {
                    throw $e;
                }   
			} elseif ($value->type == "friend") {
			    $value->delete();
                User::sendWelcomeEmailforFriends($value->user_id, $value->name, $value->email); 
			}
		}

	}

	//share post and notification facebook background
	public function getSharePostAndNotificationBackground() {
        1;
		$start = round(microtime(true) * 1000);

		$data = CrontabSchedules::getDataScheduleFacebook();

		foreach ($data as $value) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			if ($value->type_user == "postNotify") {
				$value->delete();
				UserJobController::postShareNotification($value->job_id, $value->link, $value->user_id);
			} elseif ($value->type_user == "postLink") {
				$value->delete();
				FacebookHelper::PostLinks($value->messages, $value->link, $value->email, $value->name);
			}
			
		}

	}

	//get crawled data and save to temporary table
	public function getCrawledDataAndSaveToTemporaryTable() {

		$start = round(microtime(true) * 1000);

		$crawledLink = CrawledLinks::getOneUrlIsPending();

		$url = '';

		if (count($crawledLink) == 0) return;

		$url = $crawledLink->link;

		$content = file_get_contents($url);

		$html = str_get_html($content);
		foreach($html->find('table[id=data_table]', 0)->find('tr') as $tr) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			try {

				$values = $tr->find('td');
				if (count($values)) {

					$crawled = new CrawledDataTemporary();

					if ($crawled::countDataById($values[22]->plaintext) == 0) {
						$crawled->job_category = $values[0]->plaintext;
						$crawled->job_title = $values[1]->plaintext;
						$crawled->company_name = $values[2]->plaintext;
						$crawled->posted_by = $values[3]->plaintext;
						$crawled->description = $values[4]->plaintext;
						$crawled->job_requirements = $values[5]->plaintext;
						$crawled->address = $values[6]->plaintext;
						$crawled->contact_phone = $values[7]->plaintext;
						$crawled->contact_email = $values[8]->plaintext;
						$crawled->job_category_2 = $values[9]->plaintext;
						$crawled->industry = $values[10]->plaintext;
						$crawled->employment_type = $values[11]->plaintext;
						$crawled->working_hours = $values[12]->plaintext;
						$crawled->shift_pattern = $values[13]->plaintext;
						$crawled->salary = $values[14]->plaintext;
						$crawled->job_level = $values[15]->plaintext;
						$crawled->min_years_experience = $values[16]->plaintext;
						$crawled->no_vacancies = $values[17]->plaintext;
						$crawled->no_viewed_job = $values[18]->plaintext;
						$crawled->no_applied_job = $values[19]->plaintext;
						$crawled->posting_date = (new DateTime($values[20]->plaintext));
						$crawled->closing_date = (new DateTime($values[21]->plaintext));
						$crawled->job_id = $values[22]->plaintext;
						$crawled->job_page = $values[23]->plaintext;
						$crawled->job_category_page = $values[24]->plaintext;
						$crawled->origin_url = $values[25]->plaintext;
						$crawled->origin_pattern = $values[26]->plaintext;
						$crawled->created_at_data = $values[27]->plaintext;
						$crawled->updated_at_data = $values[28]->plaintext;
						$crawled->pinged_at = $values[29]->plaintext;

						$crawled->save();
					}

				}

			} catch (Exception $e) {
				continue;
			}
		}

		$crawledLink->status = 'crawled';
		$crawledLink->save();

	}

	//get crawled data and process insert to table jobs, company, job_info, conpany_info
	public function getCrawledDataAndProcessSaveJobsCompanys() {

		$start = round(microtime(true) * 1000);

		$crawledData = new CrawledDataTemporary();
		foreach ($crawledData::getDataHaveNotExpiry() as $data) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			// check exist job by job_id
			if (Jobs::countDataByJobId($data->job_id) == 0) {
				
				$company = new Company();
				$conpanyInfo = new CompanyInfo();
				$job = new Jobs();
				$jobInfo = new JobInfos();

				$conpanyData = Company::getCompanyByName($data->company_name == 'null' ? $data->posted_by : $data->company_name);
				if (count($conpanyData) == 0) {

					//insert into table company
					$company->name = ($data->company_name == 'null' && $data->posted_by == 'null') ? 'Company name is not disclosed' : ($data->company_name == 'null' ? $data->posted_by : $data->company_name);

					$company->email = $data->contact_email != 'null' ? $data->contact_email : 'company_id@job-fw.sg';
					$company->address = $data->address;
					$company->account_pwd = md5('password');
					$company->manual = 2;
					$company->logo = 'uploads/logo_company_default.png';

					$company->save();

					if ($data->contact_email == 'null') {
						$company->email = $company->id . '@job-fw.sg';
						$company->save();
					}

					//insert into table company_info
					$conpanyInfo->company_id = $company->id;
					$conpanyInfo->save();
					
				}

				//insert into table jobs
				$job->salary_range = $data->salary;

				//check exist industry
				$dataIndustry = $data->industry;
				$dataIndustry = substr($dataIndustry, 0, strpos($dataIndustry, ' '));

				if (preg_match('/Information Technology/', $data->industry)) {
					$dataIndustry = 'InformationTechnology';
				}

				if (preg_match('/F & B/', $data->industry)) {
					$dataIndustry = 'FB';
				}

				if ($dataIndustry == 'Accounting') {
					$industryId = Industry::getIdByName('Accounting');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Automobile') {
					$industryId = Industry::getIdByName('Automobile');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Finance') {
					$industryId = Industry::getIdByName('Finance');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Beauty') {
					$industryId = Industry::getIdByName('Beauty & Wellness');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Computer') {
					$industryId = Industry::getIdByName('Computer/IT');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'IT') {
					$industryId = Industry::getIdByName('Computer/IT');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'InformationTechnology') {
					$industryId = Industry::getIdByName('Computer/IT');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Construction') {
					$industryId = Industry::getIdByName('Construction');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Education') {
					$industryId = Industry::getIdByName('Educational');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'FB') {
					$industryId = Industry::getIdByName('F & B');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Non-profit') {
					$industryId = Industry::getIdByName('Non-profit');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Insurance') {
					$industryId = Industry::getIdByName('Insurance');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Legal') {
					$industryId = Industry::getIdByName('Legal');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Logistics') {
					$industryId = Industry::getIdByName('Logistics');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Management') {
					$industryId = Industry::getIdByName('Management Consultancy');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Manufacture') {
					$industryId = Industry::getIdByName('Manufacturing');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Publishing') {
					$industryId = Industry::getIdByName('Media/Publishing/Printing');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Printing') {
					$industryId = Industry::getIdByName('Media/Publishing/Printing');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Music') {
					$industryId = Industry::getIdByName('Media/Publishing/Printing');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Medial') {
					$industryId = Industry::getIdByName('Medial');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Property') {
					$industryId = Industry::getIdByName('Property');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Public') {
					$industryId = Industry::getIdByName('Public sector');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Research') {
					$industryId = Industry::getIdByName('Research');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Telecommunications') {
					$industryId = Industry::getIdByName('Telecommunication');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Tourism') {
					$industryId = Industry::getIdByName('Tourism');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Trading') {
					$industryId = Industry::getIdByName('Trading');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Wholesale') {
					$industryId = Industry::getIdByName('Wholesale');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Retail') {
					$industryId = Industry::getIdByName('Retail');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} elseif ($dataIndustry == 'Other') {
					$industryId = Industry::getIdByName('Others');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				} else {
					$industryId = Industry::getIdByName('Others');
					$job->industry_id = $industryId;
					Industry::increaseNumberJob($industryId);
				}

				//check exist job type
				$dataType = $data->employment_type;
				$typeId = '';

				if (preg_match('/full time/', strtolower($dataType))) {
					$typeId .= JobTypes::getIdByType('Full time') . ',';
					JobTypes::IncreaseJobNumber(JobTypes::getIdByType('Full time'));
				} elseif (preg_match('/permanent/', strtolower($dataType))) {
					$typeId .= JobTypes::getIdByType('Full time') . ',';
					JobTypes::IncreaseJobNumber(JobTypes::getIdByType('Full time'));
				}

				if (preg_match('/part time/', strtolower($dataType))) {
					$typeId .= JobTypes::getIdByType('Part time') . ',';
					JobTypes::IncreaseJobNumber(JobTypes::getIdByType('Part time'));
				}

				if (preg_match('/intern/', strtolower($dataType))) {
					$typeId .= JobTypes::getIdByType('Intern') . ',';
					JobTypes::IncreaseJobNumber(JobTypes::getIdByType('Intern'));
				}

				if (preg_match('/contract/', strtolower($dataType))) {
					$typeId .= JobTypes::getIdByType('Contract') . ',';
					JobTypes::IncreaseJobNumber(JobTypes::getIdByType('Contract'));
				}

				$job->type_id = (empty($typeId) ? '0' : substr($typeId, 0, strlen($typeId) - 1));
				
				//JobFunctions
				$category = $data->job_category_2;
				$functionId = '';
				if (preg_match('/Accounting \/ Auditing \/ Taxation/', $category)) {
					$functionId .= JobFunctions::getIdByName('Accounting/Auditing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Accounting/Auditing'));
				}
				if (preg_match('/Admin \/ Secretarial /', $category)) {
					$functionId .= JobFunctions::getIdByName('Administrative') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Administrative'));
				}
				if (preg_match('/Advertising \/ Media/', $category)) {
					$functionId .= JobFunctions::getIdByName('Advertising') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Advertising'));
				}
				if (preg_match('/Architecture \/ Interior Design/', $category)) {
					$functionId .= JobFunctions::getIdByName('Design') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Design'));
				}
				if (preg_match('/Banking and Finance/', $category)) {
					$functionId .= JobFunctions::getIdByName('Finance') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Finance'));
				}
				if (preg_match('/Building and Construction/', $category)) {
					$functionId .= JobFunctions::getIdByName('Manufacturing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Manufacturing'));
				}
				if (preg_match('/Consulting/', $category)) {
					$functionId .= JobFunctions::getIdByName('Consulting') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Consulting'));
				}
				if (preg_match('/Customer Service/', $category)) {
					$functionId .= JobFunctions::getIdByName('Customer Service') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Customer Service'));
				}
				if (preg_match('/Design/', $category)) {
					$functionId .= JobFunctions::getIdByName('Design') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Design'));
				}
				if (preg_match('/Education and Training/', $category)) {
					$functionId .= JobFunctions::getIdByName('Education') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Education'));
				}
				if (preg_match('/Engineering/', $category)) {
					$functionId .= JobFunctions::getIdByName('Engineering') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Engineering'));
				}
				if (preg_match('/Entertainment/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Environment \/ Health/', $category)) {
					$functionId .= JobFunctions::getIdByName('Health Care Provider') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Health Care Provider'));
				}
				if (preg_match('/Events \/ Promotions/', $category)) {
					$functionId .= JobFunctions::getIdByName('Marketing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Marketing'));
				}
				if (preg_match('/F&B/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/General Management/', $category)) {
					$functionId .= JobFunctions::getIdByName('Management') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Management'));
				}
				if (preg_match('/General Work/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Healthcare \/ Pharmaceutical/', $category)) {
					$functionId .= JobFunctions::getIdByName('Health Care Provider') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Health Care Provider'));
				}
				if (preg_match('/Hospitality/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Human Resources/', $category)) {
					$functionId .= JobFunctions::getIdByName('Human Resources') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Human Resources'));
				}
				if (preg_match('/Information Technology/', $category)) {
					$functionId .= JobFunctions::getIdByName('Information Technology') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Information Technology'));
				}
				if (preg_match('/Insurance/', $category)) {
					$functionId .= JobFunctions::getIdByName('Finance') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Finance'));
				}
				if (preg_match('/Legal/', $category)) {
					$functionId .= JobFunctions::getIdByName('Legal') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Legal'));
				}
				if (preg_match('/Logistics \/ Supply Chain/', $category)) {
					$functionId .= JobFunctions::getIdByName('Supply Chain') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Supply Chain'));
				}
				if (preg_match('/Manufacturing/', $category)) {
					$functionId .= JobFunctions::getIdByName('Manufacturing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Manufacturing'));
				}
				if (preg_match('/Marketing \/ Public Relations/', $category)) {
					$functionId .= JobFunctions::getIdByName('Marketing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Marketing'));
				}
				if (preg_match('/Medical \/ Therapy Services/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}
				if (preg_match('/Personal Care \/ Beauty/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}
				if (preg_match('/Professional Services/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Public \/ Civil Service/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}
				if (preg_match('/Purchasing \/ Merchandising/', $category)) {
					$functionId .= JobFunctions::getIdByName('Purchasing') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Purchasing'));
				}
				if (preg_match('/Real Estate \/ Property Management/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Repair and Maintenance/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Risk Management/', $category)) {
					$functionId .= JobFunctions::getIdByName('Finance') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Finance'));
				}
				if (preg_match('/Sales \/ Retail/', $category)) {
					$functionId .= JobFunctions::getIdByName('Sales') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Sales'));
				}
				if (preg_match('/Sciences \/ Laboratory/', $category)) {
					$functionId .= JobFunctions::getIdByName('Science') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Science'));
				}
				if (preg_match('/Security and Investigation/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}
				if (preg_match('/Social Services/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}
				if (preg_match('/Telecommunications/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Travel \/ Tourism/', $category)) {
					$functionId .= JobFunctions::getIdByName('General Business') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('General Business'));
				}
				if (preg_match('/Others/', $category)) {
					$functionId .= JobFunctions::getIdByName('Other') . ',';
					JobFunctions::IncreaseJobNumber(JobFunctions::getIdByName('Other'));
				}

				$job->function_id = (empty($functionId) ? '0' : substr($functionId, 0, strlen($functionId) - 1));

				$job->status = 'open';

				if (count($conpanyData)) {
					$job->company_id = $conpanyData->id;
				} else {
					$job->company_id = $company->id;
				}

				$job->confirm_status = 'confirmed';
				$job->price = 0;
				$job->hiring_bonus = 0;
				$job->total_reward = 0;
				$job->manual = 2;
				$job->closing_date = $data->closing_date;
				$job->job_id = $data->job_id;

				$job->save();

				//insert into table job_info
				$jobInfo->job_id = $job->id;
				$jobInfo->headline = $data->job_title;
				$jobInfo->experience = ($data->job_level == '-' ? 'Not Applicable' : $data->job_level);
				$jobInfo->requirements = $data->job_requirements;
				$jobInfo->responsibilities = $data->description;
				$jobInfo->country = 'Singapore';
				$jobInfo->job_link = $data->job_page;

				$jobInfo->save();

			}

		//delete crawled job when was inserted
		$data->delete();

		}

	}

	//check job is closing date then delete
	public function getCheckClosingDateForAllJobs() {

		$start = round(microtime(true) * 1000);

		CrawledDataTemporary::deleteDataWhenIsClosing();

		foreach (Jobs::getJobIsClosing() as $job) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			$job->status = 'closed';
			$job->save();

			Industry::reductionNumberJob($job->industry_id);

			foreach (explode(',', $job->type_id) as $typeId) {
				JobTypes::reductionNumberJob($typeId);
			}

			foreach (explode(',', $job->function_id) as $functionId) {
				JobFunctions::reductionNumberJob($functionId);
			}

		}

	}
    
    //send daily email
	public function getSendDailyEmailUpdateToAdmin() {
	   date_default_timezone_set('Singapore');
       $today = strtotime(date('Y-m-d'));
	   $yesterday = strtotime(date('Y-m-d')) - 24 * 60 * 60;
       $weekAgo = $yesterday - 6 * 24 * 60 * 60;
       $monthAgo = $yesterday - 29 * 24 * 60 * 60;
       $yesterdayInfo = CrontabSchedules::getDetailByTime($yesterday, $today);
       $weekAgoInfo = CrontabSchedules::getDetailByTime($weekAgo, $today);
       $monthAgoInfo = CrontabSchedules::getDetailByTime($monthAgo, $today);
       $totalInfo = CrontabSchedules::getDetailByTime(0,$today);
       $username = 'Admin';
       
       $data = array(
                   'yesterday'  => $yesterdayInfo,
                   'weekAgo'    => $weekAgoInfo,
                   'monthAgo'   => $monthAgoInfo,
                   'total'      => $totalInfo
               );
       Mail::send('emails.daily', $data, function ($message) use ($username) {               
          $message->to('dailyreport@job-fw.com', $username)->subject('Daily Mail');                                             
       });       
	}
    /*
    public function getSendMessageEmail() {
	   $list = CrontabSchedules::where('type', 'message')->get()->toArray();        
        if (count($list)) {
            try{
                $list = array_slice($list, 0, 10); 
                foreach ($list as $index=>$message) {
                    if ($message['type_user']) {
                        MailHelper::SendApply_MessageEmailForCompany($message['job_id'],'emails.company_message_content',$message['messages']);
                    } else {
                        MailHelper::Send('MessagesChat.user_message_email_content',array('username'=>$message['name']),$message['email'],$message['messages']);
                    }
                    CrontabSchedules::where('id','=',$message['id'])->delete();  
                }
            } catch (Exception $e) {
                throw $e;
            }
        }
	}
    */
	public function getSaveLinks() {

		$start = round(microtime(true) * 1000);

		$content = file_get_contents('https://getdata.io/data_sets/31#data-sources');

		$html = str_get_html($content);
		foreach($html->find('div[id=data-sources]', 0)->find('table[class=table]', 0)->find('tr') as $tr) {

			$end = round(microtime(true) * 1000);

			if (($end - $start) >= (1000*60*1)) return;

			$values = $tr->find('td');
			if (count($values)) {

				$link = 'https://getdata.io' . $values[0]->find('a', 0)->href;
				$lastRan = $values[3]->plaintext;
				
				$linkExist = CrawledLinks::getLink($link);

				if (count($linkExist)) {

					if ($lastRan != $linkExist->last_ran) {

						$linkExist->status = 'pending';
						$linkExist->last_ran = $lastRan;
						$linkExist->save();
						
					}

				} else {
					
					$crawledLink = new CrawledLinks();
					$crawledLink->link = $link;
					$crawledLink->status = 'pending';
					$crawledLink->last_ran = $lastRan;
					$crawledLink->save();

				}
			}
		}
	}
    
    public static function getUpdateProfile(){
        $list = CrontabSchedules::where('type', 'login')->get()->toArray();        
        if (count($list)) {
            try{
                
            $list = array_slice($list, 0, 5); 
            foreach ($list as $index=>$login) {
                $uid = $login['email'];
                $facebook = new Facebook(Config::get('facebook'));        
                $me = $facebook->api('/'.$uid);
                $fb_token = FbToken::whereFb_id($uid)->first();
                $first_name = false;
                $last_name = false;
                $email = false;
                $location=false;
                //check and get user information
                if (array_key_exists('first_name', $me)) {
                    $first_name = $me['first_name'];
                }
                if (array_key_exists('last_name', $me)) {
                    $last_name = $me['last_name'];
                }
                if (array_key_exists('email', $me)) {
                    $email = $me['email'];
                }
                if(array_key_exists('location',$me)) {
                    $locationobj=$me['location'];
                    $location=$locationobj['name'];
                }
                if (empty($fb_token)) {
                    //Create new user
                    $user = new User;
    
                    if ($first_name) {
                        $user->name = $first_name . ' ';
                    }
                    if ($last_name) {
                        $user->name .= $last_name;
                    }
                    if ($email) {
                        $user->email = $email;
                    }
                    $user->avatar = 'https://graph.facebook.com/' . $me['id'] . '/picture?type=large';
                    if($location) {
                        $user->location=$location;
                    }
    
                    $user->save();
    
                    // create new user resume
    
                    UserResumes::createNewDefault($user->id);
    
                    //Create new fb_token
                    $fb_token = new FbToken();
                    $fb_token->fb_id = $uid;
                    $Fbtoken = $fb_token->token = $login['name'];
                    $fb_token = $user->fbTokens()->save($fb_token);
    
                    $FbFriend = Friends::ListFriendFB($Fbtoken);
                    foreach ($FbFriend as $value) {
                        $frend = new Friends();
                        $frend->fb_id = $uid;
                        $frend->user_id = $user->id;
                        $frend->to_user_id = $value['user_id'];
                        $frend->to_fb_id = $value['fb_id'];
    
                        $frend->save();
    
                        // send email to crontab
                        $message = new CrontabSchedules;
                        $message->type = 'friend';
                        $message->email = $user->avatar;
                        $message->name = $user->name;
                        $message->user_id = $value['user_id'];
                        $message->save();
    
                        // send facebook notification
                        $template="Your friends @[$uid] just join to JobForward.";
                        FacebookHelper::postNotification($value['fb_id'],URL::to('/'),$template);
                    }
                    // send email for new user
                    User::sendWelcomeEmail($user->id);
    
                    // is new user
                    Session::put('is_new_user_login', 'true');
    
                } else {
                    $user_update = User::whereid($fb_token->user_id)->first();
                    if ($user_update) {
                        //reset user name
                        $user_update->name = '';
                        if ($first_name) {
                            $user_update->name = $first_name . ' ';
                        }
                        if ($last_name) {
                            $user_update->name .= $last_name;
                        }
                        if ($email) {
                            $user_update->email = $email;
                        }
                        $user_update->avatar = 'https://graph.facebook.com/' . $me['id'] . '/picture?type=large';
    
                        if($location) {
                            $user_update->location=$location;
                        }
                        $user_update->save();
    
                        $fb_token->token=$login['name'];
                        $fb_token->save();
    
                        Friends::deleteFriendFB($user_update->id);
    
                        $FbFriend = Friends::ListFriendFB($login['name']);
                        foreach ($FbFriend as $value) {
                            $frend = new Friends();
                            $frend->fb_id = $uid;
                            $frend->user_id = $user_update->id;
                            $frend->to_user_id = $value['user_id'];
                            $frend->to_fb_id = $value['fb_id'];
    
                            $frend->save();
                        }
    
                    }
                    else
                    {
                        throw new Exception("User not found");
                    }
                }
    
    
                $fb_token->token = $login['name'];
                $fb_token->save();
                $userID = FbToken::where('token', $login['name'])->get(array('user_id'))->first();
    
                if($userID)
                {
                    // date education history and work history
                    $eduword = $facebook->api('/'.$uid.'?fields=education,work');
    
                    LoginController::getFacebookInformation($userID['user_id'], $eduword);
                    //exit;
                    // echo  "OK"; exit;
    
                    Wallets::initCoin($userID['user_id']);
    
                    //Session::put('user_id', $userID['user_id']);
                    //Session::put('user_name', $me['username']);
                    Session::put('name', $me['first_name'] . ' ' . $me['last_name']);
                    Session::put('avatar', 'https://graph.facebook.com/' . $me['id'] . '/picture?type=large');
    
                    if(!isset($_GET['fwd']))
                    {
                        //return Redirect::to('/jobs');
                    }
                    else
                    {
                        if(isset($_GET['flag']))
                        {
                            Session::put('flag_share_apply', $_GET['flag']);
                            if(isset($_GET['mess']))
                            {
                                Session::put('mess_share_facebook', $_GET['mess']);
                            }
                        }
                        //return Redirect::to($_GET["fwd"]);
                    }
    
                }
                else
                {
                    throw new Exception("User not found");
                }
                CrontabSchedules::where('email','=',$uid)->delete();        
            }
          } catch (Exception $e) {
            throw $e;
          }
        }
    }        
}