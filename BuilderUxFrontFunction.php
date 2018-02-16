<?php
/*
 * BuilderUxFrontFunction.php
 * 
 * Copyright 2016 BuilderUx <contact@builderux.com>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */
?>
<?php 
/**
 * get lenders 
 * param int (subdivision id) 
 * @return array
 */
function get_builderux_lenders($subdivision)
{
	global $wpdb;
	$prefix=$wpdb->prefix;
	
	 $sql="SELECT l.*,s.DivisionID  FROM `{$prefix}builder_lenders` l LEFT JOIN {$prefix}builder_subdivision s ON s.ID={$subdivision}  Left JOIN {$prefix}builder_division d On d.ID=s.DivisionID where l.lender_status='on' AND l.UserDefined1='1' AND l.CompanyName LIKE CONCAT('%',(SELECT trim(LegalName) FROM {$prefix}builder_subdivision WHERE ID = {$subdivision}),'%')  AND l.DivisionID like s.DivisionID";
	 if(isset($_GET['test'])){
		$sql.=" and l.UserDefined1=1";	
	 }
	$records=$wpdb->get_results($sql);
	if(empty($records)){
		$sql="SELECT l.*,s.DivisionID  FROM `{$prefix}builder_lenders` l LEFT JOIN {$prefix}builder_subdivision s ON s.ID={$subdivision}  Left JOIN {$prefix}builder_division d On d.ID=s.DivisionID where l.lender_status='on' AND l.UserDefined1='1' AND l.DivisionID like s.DivisionID";
		if(isset($_GET['test'])){
			$sql.=" and l.UserDefined1=1";	
		 }
		$records=$wpdb->get_results($sql);
	}
	return $records;
}
/**
* @method display field
* @param array
* @return html
**/
function display_buynow_field($field)
{
	$html='';
	switch ($field->field_type)
	{
		case 'text':
			$required=$field->field_required==1 ? 'data-require="true"' : '';
			$html='<input id="'.$field->field_name.'" class="form-control" type="text" '.$required.' name="TOPObuy['.$field->field_name.']" placeholder="'.$field->field_label.'">';
		break;
		case 'email':
			$required=$field->field_required==1 ? 'data-require="true"' : '';
			$html='<div id="'.$field->field_name.'" class="common_'.$field->field_name.'"><input data-validate="email" class="form-control" id="'.$field->field_name.'" type="text" '.$required.' name="TOPObuy['.$field->field_name.']" placeholder="'.$field->field_label.'"><div class="'.$field->field_name.'"></div></div>';
		break;
		case 'textarea':
			$required=$field->field_required==1 ? 'data-require="true"' : '';
			$html='<textarea id="'.$field->field_name.'" class="form-control" '.$required.' name="TOPObuy['.$field->field_name.']" placeholder="'.$field->field_label.'"></textarea>';
		break;
		case 'select':
			if($field->option_enabled)
			{
				$options=explode(',',$field->option_enabled);
				$html.='<select id="'.$field->field_name.'" name="TOPObuy['.$field->field_name.']" class="form-control">';
					$html.='<option value="">-select option-</option>';
					foreach($options as $key=>$option)
					{
						$html.='<option value="'.$option.'">'.$option.'</option>';
					}
				$html.='</select>';
			}
		break;
		
		case 'radio':
			if($field->option_enabled)
			{
				$options=explode(',',$field->option_enabled);
				$html.='<div>';
				foreach($options as $key=>$option)
				{
					$html.='<input id="'.$field->field_name.'" name="TOPObuy['.$field->field_name.']" value="'.$option.'" type="radio"/> '.$option.'<br>';
                }
				$html.='</div>';
			}
		break;
	}	
	
	echo  $html;
}
/**
* Function builderux_docusign_pdfversion
*
* param array int reservation_id
* @return mixed,
**/
function builderux_docusign_pdfversion($reservation_id)
{
	global $wpdb;
	$option=get_option('builderux_BuyNow_setting');
	$reservation_data=get_reservation_data($reservation_id);
	if(is_lotstatus($reservation_data->lotid)!=true){
		return builderUX_flash('danger',"Your lot is invalid.Please Try again.");
		exit;
	}
	$lot_datas = get_bxdata_byID('builder_phaselot','ID',$reservation_data->lotid);
	$lot_promo_price = isset($lot_datas->UserDefinedPreplot1) ? $lot_datas->UserDefinedPreplot1: '';
	$phaseplan_data = get_bxdata_byID('builder_phaseplan','ID',$lot_datas->PhasePlanID);
	$subdivision_data = get_bxdata_byID('builder_subdivision','ID',$reservation_data->division_id);
	
	if(empty($reservation_data)){
		return builderUX_flash('danger',"Your reservation not found so you can not proceed to docusign.");
	}
	if(empty($subdivision_data))
		return builderUX_flash('danger',"Invalid Data.Please try again or contact to admin");
	$detail_data=get_option('builderux_BuyNow_setting');
	if(!isset($reservation_data->transection_id) && !empty($reservation_data->transection_id)){
  		return builderUX_flash('danger',"Payment is Needed To Complete Reservation");
		die;
	}
	if($reservation_data->gotocontact!='yes'){
		return builderUX_flash('danger',"Your request is invalid.Please Try again.");
		exit;
	}
	set_time_limit(500);
	$docusigndetail=BxGetDocusign($subdivision_data);
	if((!isset($docusigndetail['DSLogin'])) || (!isset($docusigndetail['DSPass'])))
		return builderUX_flash('danger',"Error occurred with connecting to DocuSign please contact us .");
	//userinfo
    $doc_data='';
	//replace shortcodes
	
	$Lot_City = isset($lot_datas->City) ? $lot_datas->City: '';
	$Lot_County = isset($lot_datas->Country) ? $lot_datas->Country: '';
	$Lot_State = isset($lot_datas->State) ? $lot_datas->State: '';
	$Lot_Address1 = isset($lot_datas->Address1) ? $lot_datas->Address1: '';
	$Lot_Zip = isset($lot_datas->Zip) ? $lot_datas->Zip: '';
	$Lot_Number = isset($lot_datas->JobUnitNum) ? $lot_datas->JobUnitNum: '';
	$Subdivision_Marketing_Name = isset($reservation_data->MarketingName) ? $reservation_data->MarketingName: '';
	$Demo10 = isset($reservation_data->financetype) ? $reservation_data->financetype: '____';
	$co_buyer_first_name = isset($reservation_data->co_buyer_first_name) ? $reservation_data->co_buyer_first_name: ' ';
	$co_buyer_last_name = isset($reservation_data->co_buyer_last_name) ? $reservation_data->co_buyer_last_name: ' ';
	$Demo1 = isset($lot_datas->Demo1) ? $lot_datas->Demo1: '';
	$Demo2 = isset($lot_datas->Demo2) ? $lot_datas->Demo2: '';
	$Broker_Name = isset($lot_datas->Broker_Name) ? $lot_datas->Broker_Name: '';
	$Realtor_License = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$Demo39 = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$HOA_Fees = isset($subdivision_data->HOAFees) ? $subdivision_data->HOAFees: '';
	$Misc_Fee = isset($subdivision_data->MiscellaneousFee) ? $subdivision_data->MiscellaneousFee: '';
	$Setup_Fee = isset($subdivision_data->SetupFee) ? $subdivision_data->SetupFee: '';
	$Broker_Company = isset($lot_datas->company_fields) ? $lot_datas->company_fields: '';
	$company_fields = isset($reservation_data->company_fields) ? $reservation_data->company_fields: '';
	$Realtor_License = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$first_name=isset($reservation_data->FirstName) ? $reservation_data->FirstName: '';
	$last_name=isset($reservation_data->LastName) ? $reservation_data->LastName: '';
	$plan_marketing_name = isset($phaseplan_data ->MarketingName ) ? $phaseplan_data ->MarketingName : '';
	$garage_orientation = isset($lot_datas->GarageOrientation) ? $lot_datas->GarageOrientation: '____';
	$Total_Prices = isset($lot_datas->LotTotalPrice) ? $lot_datas->LotTotalPrice: '';
	$discount =!empty($detail_data['discount'])?$detail_data['discount']:'';
	$Discount_price =!empty($discount)?$discount:'NA';
	$color_scheme = isset($lot_datas->ColorScheme) ? $lot_datas->ColorScheme: '_____';
	$Buyer_Address1 = isset($reservation_data->address1) ? $reservation_data->address1: '';
	$Buyer_City = isset($reservation_data->city) ? $reservation_data->city: '';
	$StreetAddress = isset($reservation_data->StreetAddress) ? $reservation_data->StreetAddress: '';
	$BuyerPhone =  isset($reservation_data->Phone) ? $reservation_data->Phone: '';
	$Buyer_State = isset($reservation_data->state) ? $reservation_data->state: '';
	$Buyer_Zip = isset($reservation_data->Buyer_Zip) ? $reservation_data->Buyer_Zip: '';
	$agentEmail = isset($subdivision_data->Email) ? $subdivision_data->Email: '';
	$agentName = empty($subdivision_data->Phone) ?'Sales Agent': $subdivision_data->Phone;
	$co_buyer_email = isset($reservation_data->co_buyer_email_address) ? $reservation_data->co_buyer_email_address: '';
	$Realtor_name = isset($reservation_data->Realtor_name) ? $reservation_data->Realtor_name: '___________________';
	$realtor_email=isset($reservation_data->realtor_email__address) ? $reservation_data->realtor_email__address: '';
	$realtor_license_number = isset($reservation_data->realtor_license_number) ? $reservation_data->realtor_license_number: '___________________';
	$closingDate = isset($lot_datas->LegalAddress1) ? $lot_datas->LegalAddress1: '';
	$Deposits_Paid = isset($option['standard_trans_fee']) ? $option['standard_trans_fee']: '';
	$closing_costs= (isset($reservation_data->closing_cost) && $reservation_data->closing_cost) ? $reservation_data->closing_cost: 0;
	$financetype=$reservation_data->financetype;
	$Total_Prices = empty($lot_promo_price)?$Total_Prices:$lot_promo_price;
	$Total_Price = $Total_Prices+$closing_costs;
	$Balance = $Total_Price-$Deposits_Paid;
	$sellerEmail = (isset($subdivision_data->Service1) && filter_var($subdivision_data->Service1, FILTER_VALIDATE_EMAIL)) ? $subdivision_data->Service1: 'bsink@wadejurneyhomes.com';
	if($Balance>$discount){
	$Balance_price = $Balance-$discount;
	}else{ 
		$Balance_price = $Balance;
	}
	$fhacheckbox=$vacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/unchecked.png'/>";
	$isContracttype=false;
	if($financetype=='FHA'){
		//$fhacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/checked.png'/>";
		$isContracttype=true;
	}elseif($financetype=='VA'){
		//$vacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/checked.png'/>";
		$isContracttype=true;
	}
	$co_buyer_suffix=isset($reservation_data->co_buyer_suffix) ? $reservation_data->co_buyer_suffix : '';
	$co_buyer_middle_initial=isset($reservation_data->co_buyer_middle_initial) ? $reservation_data->co_buyer_middle_initial : '';
	$cobuyerfullname=$co_buyer_suffix;
	$cobuyerfullname.=' '.$co_buyer_first_name;
	$cobuyerfullname.=' '.$co_buyer_middle_initial;
	$cobuyerfullname.=' '.$co_buyer_last_name;
	$buyerfullname=isset($reservation_data->suffix) ? $reservation_data->suffix: ''; 
	$buyerfullname.=' '.$first_name;
	$buyerfullname.=isset($reservation_data->middle_name) ? ' '.$reservation_data->middle_name: ''; 
	$buyerfullname.=' '.$last_name;
	
	$documentName = $Subdivision_Marketing_Name.'-'.$Lot_Number.'-'.$first_name; // document name for docusign
	$shortcodes=array('{{buyerfullname}}','{{cobuyerfullname}}','{{lot_name}}','{{division}}','{{subdivision}}','{{lotprice}}','{{contract_date}}','{{buyer_name}}','{{co_buyer_first_name}}','{{StreetAddress}}','{{Phone}}','{{Lot_City}}','{{Lot_County}}','{{Lot_State}}','{{Lot_Address1}}','{{Lot_Zip}}','{{Lot_Number}}','{{Subdivision_Marketing_Name}}','{{Demo10}}','{{Demo1}}','{{Broker_Name}}','{{Realtor_License}}','{{Demo39}}','{{HOA_Fees}}','{{Misc_Fee}}','{{Setup_Fee}}','{{Broker_Company}}','{{Realtor_License}}','{{Buyer_Address1}}','{{city}}','{{state}}','{{Buyer_Zip}}','{{plan_marketing_name}}','{{garage_orientation}}','{{color_scheme}}','{{Total_Price}}','{{Deposits_Paid}}','{{Balance_price}}','{{closing_costs}}','{{Demo2}}','{{Realtor_name}}','{{realtor_license_number}}','{{company_fields}}','{{closingDate}}','{{lot_City}}','{{lot_County}}','{{lot_State}}','{{lot_Address}}','{{lot_Zip}}','{{Lot_Number}}','{{Subdivision_Marketing_Name}}','{{HOA_Fees}}','{{Misc_Fee}}','{{Broker_Name}}','{{Realtor_License}}','{{Discount_price}}');
	$values=array($buyerfullname,$cobuyerfullname,isset($reservation_data->JobUnitNum)?$reservation_data->JobUnitNum:'',isset($reservation_data->div_name)?$reservation_data->div_name:'',isset($reservation_data->MarketingName)?$reservation_data->MarketingName:'', isset($reservation_data->LotTotalPrice)?$reservation_data->LotTotalPrice:'',date("Y-m-d"),$first_name.' '.$last_name,$co_buyer_first_name,$StreetAddress,$BuyerPhone,$Lot_City,$Lot_County,$Lot_State,$Lot_Address1,$Lot_Zip,$Lot_Number,$Subdivision_Marketing_Name,$Demo10,$Demo1,$Broker_Name,$Realtor_License,$Demo39,$HOA_Fees,$Misc_Fee,$Setup_Fee,$Broker_Company,$Realtor_License,$Buyer_Address1,$Buyer_City,$Buyer_State,$Buyer_Zip,$plan_marketing_name,$garage_orientation,$color_scheme,$Total_Price,$Deposits_Paid,$Balance_price,$closing_costs,$Demo2,$Realtor_name,$realtor_license_number,$company_fields,$closingDate,$Lot_City,$Lot_County,$Lot_State,$Lot_Address1,$Lot_Zip,$Lot_Number,$Subdivision_Marketing_Name,$HOA_Fees,$Misc_Fee,$Broker_Name,$Realtor_License,$Discount_price); 
	$recipientEmail=isset($reservation_data->Email) ? $reservation_data->Email: '';
	$first_name=isset($reservation_data->FirstName) ? $reservation_data->FirstName: '';
	$last_name=isset($reservation_data->LastName) ? $reservation_data->LastName: '';
	$recipientName=$first_name.' '.$last_name;
	$subname=isset($subdivision_data->MarketingName) ? $subdivision_data->MarketingName : '';
	$lotjobnum=isset($lot_datas->JobUnitNum) ? $lot_datas->JobUnitNum : '';
	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($reservation_data->$fieldkey) ? $reservation_data->$fieldkey : '';
		}
	}
	// get the main contract
	$PdfData=bx_fetch_byAttributes("builder_docusign_pdf",array("ContractType='assigned'","SubdivisionID='{$subdivision_data->ID}'"));
	if(empty($PdfData)){
		$PdfData=bx_fetch_byAttributes("builder_docusign_pdf",array("ContractType='global'"));
	}
	$fileArray=array();
	// conteract found
	if($PdfData){
		
		$templateid=$PdfData->template_id;
		require_once(BUILDERUX_DIR_PATH."/vendor/PdfParsing/ClassPdf.php");
		$pdfparsing=new BX_PdfParsing;
		
		//let us create a pdf file
		$ContractPdf=$pdfparsing->Bx_FillPdfData($PdfData,$shortcodes,$values);
		if($ContractPdf){
			$documentFileName=$ContractPdf['path'];
			$fileArray[]=$documentFileName;
		}else{
			return builderUX_flash('danger',"Error to get data from SS.Please contact admin."); 
		}
	}else{
		return builderUX_flash('danger',"Document not found.Please contact to Administrator"); 
	}
	// inject the Document Forms
	if(!empty($subdivision_data->LegalName) && (substr($subdivision_data->MarketingName, -2) != '25')){
    	$PdfData2=bx_fetch_byAttributes("builder_docusign_pdf",array("ContractType='{$subdivision_data->LegalName}'"));
    	
		if($PdfData2){
		    $attornyform=$pdfparsing->Bx_FillPdfData($PdfData2,$shortcodes,$values);
		    $fileArray[]=$attornyform['path'];
		}
	}

	$PdfData3=bx_fetch_byAttributes("builder_docusign_pdf",array("ContractType='fhavaform'"));
    	
	if($PdfData3){
	    $fhavaform=$pdfparsing->Bx_FillPdfData($PdfData3,$shortcodes,$values);
	    $fileArray[]=$fhavaform['path'];
	}
	if($subdivision_data->State=='NC'){
	    $PdfData4=bx_fetch_byAttributes("builder_docusign_pdf",array("ContractType='nc-mineralrights'"));
    	
    	if($PdfData4){
    	    $mineralrights=$pdfparsing->Bx_FillPdfData($PdfData4,$shortcodes,$values);
    	    $fileArray[]=$mineralrights['path']; 
    	}
	}
	$contractname=str_replace(array(" ","'"),"","res_{$reservation_id}-Sub_{$subname}-JUN_{$lotjobnum}.pdf");
	//generate pdf
	$mergedcontract=BUILDERUX_JSON_UPLOAD."/templates/{$contractname}";
    $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=$mergedcontract ";
    //Add each pdf file to the end of the command
    foreach($fileArray as $file) {
        $cmd .= $file." ";
    }
    $result = shell_exec($cmd);
    $documentFileName=$mergedcontract;
    if(!file_exists($documentFileName)){
		global $wpdb; 
		$wpdb->update( $wpdb->prefix.'builder_reservation', array('envelope_id'=>"unable to create Contract"), array('id'=>$reservation_id));
		return builderUX_flash('danger',"Unable to create Contract please contact Administrator"); 
	}
    // delete the pdf files
    foreach($fileArray as $tmpfile) {
        if(file_exists($tmpfile))
			unlink($tmpfile);
    }
    // configure the document we want signed
	$username = $docusigndetail['DSLogin'];
    $password =$docusigndetail['DSPass'];
    $integrator_key = $option['docu_integrator_key'];
    $host = $option['docu_host'];
    // create a new DocuSign configuration and assign host and header(s)
    $config = new DocuSign\eSign\Configuration();
    $config->setHost($host);
    $config->addDefaultHeader("X-DocuSign-Authentication", "{\"Username\":\"" . $username . "\",\"Password\":\"" . $password . "\",\"IntegratorKey\":\"" . $integrator_key . "\"}");
    /////////////////////////////////////////////////////////////////////////
    // STEP 1:  Login() API
    /////////////////////////////////////////////////////////////////////////
    // instantiate a new docusign api client
    $docusignlogs=array("user"=>$username);
    $apiClient = new DocuSign\eSign\ApiClient($config); 
    try 
	{
		//*** STEP 1 - Login API: get first Account ID and baseURL
		$authenticationApi = new DocuSign\eSign\Api\AuthenticationApi($apiClient);
		$options = new \DocuSign\eSign\Api\AuthenticationApi\LoginOptions();
		$loginInformation = $authenticationApi->login($options);
		if(!isset($loginInformation->errorCode) && count($loginInformation) > 0)
		{
			$loginAccount = $loginInformation->getLoginAccounts()[0];
			if(empty($loginAccount)){
			    //return builderUX_flash('danger',"Unable to login into Docusign account.");
			    return false;
			}
			$host = $loginAccount->getBaseUrl();
			$host = explode("/v2",$host);
			$host = $host[0];
			$docusignlogs['Host']=$host;
			// UPDATE configuration object
			$config->setHost($host);
			// instantiate a NEW docusign api client (that has the correct baseUrl/host)
			$apiClient = new DocuSign\eSign\ApiClient($config);
			if(isset($loginInformation))
			{
				$accountId = $loginAccount->getAccountId();
				$docusignlogs['Account']=$accountId;
				if(!empty($accountId))
				{
					//*** STEP 2 - Signature Request from a Template
					// create envelope call is available in the EnvelopesApi
					$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);
					
					// Add a document to the envelope
					$document = new DocuSign\eSign\Model\Document();
					$document->setDocumentBase64(base64_encode(file_get_contents($documentFileName)));
					$document->setName($documentName);
					$document->setDocumentId("1");
					// assign recipient to template role by setting name, email, and role name.  Note that the
					// template role name must match the placeholder role name saved in your account template.
					$templateRole = new  DocuSign\eSign\Model\TemplateRole();
					$templateRole->setEmail($recipientEmail);
					$templateRole->setName($recipientName);
					$templateRole->setRoleName("Buyer");            
					$templateRole->setClientUserId('12345');
					$docusignlogs['Recipients'][]=array("Email"=>$recipientEmail,"Name"=>$recipientName,"Role"=>"Buyer");				
					$templateRole1 = new  DocuSign\eSign\Model\TemplateRole();
					$templateRole1->setEmail($agentEmail);
					$templateRole1->setName($agentName);
					$templateRole1->setRoleName("SA"); 
					$docusignlogs['Recipients'][]=array("Email"=>$agentEmail,"Name"=>$agentName,"Role"=>"SA");            
					//$templateRole1->setClientUserId('12345');
					$all_template_roles = array($templateRole,$templateRole1);
					if(!empty($co_buyer_email)){
						$cbname=$co_buyer_first_name.' '.$co_buyer_last_name;
						$templateRole3 = new  DocuSign\eSign\Model\TemplateRole();
						$templateRole3->setEmail($co_buyer_email);
						$templateRole3->setName($cbname);
						$templateRole3->setRoleName("co-buyer");             
						//$templateRole3->setClientUserId('12345');
						$all_template_roles[]=$templateRole3;
						$docusignlogs['Recipients'][]=array("Email"=>$co_buyer_email,"Name"=>$cbname,"Role"=>"co-buyer"); 
					}
					if(!empty($realtor_email)){
						$realtorrole = new  DocuSign\eSign\Model\TemplateRole();
						$realtorrole->setEmail($realtor_email);
						$realtorrole->setName($Realtor_name);
						$realtorrole->setRoleName("Realtor");             
						//$templateRole3->setClientUserId('12345');
						$all_template_roles[]=$realtorrole;
						$docusignlogs['Recipients'][]=array("Email"=>$realtor_email,"Name"=>$Realtor_name,"Role"=>"Realtor"); 
					}
					
					// Send last email to Seller 
					$Seller_contract = new  DocuSign\eSign\Model\TemplateRole();
					//$Seller_contract->setEmail('contract@wadejurneyhomes.com');
					$Seller_contract->setEmail($sellerEmail);
					$Seller_contract->setName('Wade jurney');
					$Seller_contract->setRoleName("Seller");             
					//$templateRole3->setClientUserId('12345');
					$all_template_roles[]=$Seller_contract;
					$docusignlogs['Recipients'][]=array("Email"=>$sellerEmail,"Name"=>'Wade jurney',"Role"=>"Seller"); 
					// instantiate a new envelope object and configure settings
					$envelop_definition = new DocuSign\eSign\Model\EnvelopeDefinition();
					$envelop_definition->setEmailSubject(" E-CONTRACT – {$subname} – {$lotjobnum}");
					$envelop_definition->setTemplateId($templateid);
					$envelop_definition->setDocuments(array($document));
					$envelop_definition->setTemplateRoles($all_template_roles);
					
					// set envelope status to "sent" to immediately send the signature request
					$envelop_definition->setStatus("sent");

					// optional envelope parameters
					$options = new \DocuSign\eSign\Api\EnvelopesApi\CreateEnvelopeOptions();
					$options->setCdseMode(null);
					$options->setMergeRolesOnDraft(null);
					// create and send the envelope (aka signature request)
					$envelop_summary = $envelopeApi->createEnvelope($accountId, $envelop_definition, $options);
					if(!isset($envelop_summary->errorCode)){
						$document=json_decode($envelop_summary);
						$envloped=$document->envelopeId;
						$docusignlogs['EnvelopId']=$envloped;
						$logs=json_encode($docusignlogs);
						if((isset($_GET['test']) && $_GET['test']==true)) {
							if((isset($_GET['SubmitLead']) && $_GET['SubmitLead']==true)) {
								$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$reservation_data->id,'_envelopid'=>$envloped,'test'=>'true','SubmitLead'=>'true'),get_permalink());
								// get the contract agreement url
								$zipfile=BX_Getdocusign_pdf($document->envelopeId,$accountId,$apiClient);
								// lets submit the lead
								submit_ss_lead($reservation_data,$zipfile); // submit the lead to the sales simplicity
								//update into local DB
								$wpdb->update( $wpdb->prefix.'builder_phaselot', array('StatusName'=>'Sold'), array('id'=>$reservation_data->lotid));
							}else{
								$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$reservation_data->id),get_permalink());
							}
						}else{
							// get the contract agreement url
							$zipfile=BX_Getdocusign_pdf($document->envelopeId,$accountId,$apiClient);
							// lets submit the lead
							submit_ss_lead($reservation_data,$zipfile); // submit the lead to the sales simplicity
							$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$reservation_id,'_envelopid'=>$envloped),get_permalink());
							//update into local DB
							$wpdb->update( $wpdb->prefix.'builder_phaselot', array('StatusName'=>'Sold'), array('id'=>$reservation_data->lotid));
						}
						//update the envelopid in the table
						global $wpdb; 
						$wpdb->update( $wpdb->prefix.'builder_reservation', array('ReservationStatus'=>"Docusign Done","DocusignLogs"=>$logs,'envelope_id'=>$document->envelopeId), array('id'=>$reservation_id));
						$viewrequest = new DocuSign\eSign\Model\RecipientViewRequest();
						$viewrequest->setUserName($recipientName); 
						$viewrequest->setEmail($recipientEmail);
						$viewrequest->setAuthenticationMethod('email');
						$viewrequest->setClientUserId('12345');
						$viewrequest->setReturnUrl($ReturnUrl);
						$envelopview=$envelopeApi->createRecipientView($accountId,$document->envelopeId,$viewrequest);
						$redirecturl=$envelopview->getUrl();
						$emaillink=add_query_arg(array('EnvelopID'=>$document->envelopeId,'_uid'=>$reservation_id,'action'=>"DocusignSignature",'__U'=>$recipientName,'__E'=>$recipientEmail,'ReturnUrl'=>urlencode($ReturnUrl)),get_permalink());
						bx_Send_Docusign_email($recipientEmail,$redirecturl,$emaillink);
					}else{
						$message=isset($envelop_summary->message) ? $envelop_summary->message : "unable to create envelope";
						$wpdb->update( $wpdb->prefix.'builder_reservation', array('envelope_id'=>$message), array('id'=>$reservation_id));
						return builderUX_flash('danger',"Error occurred with connecting to DocuSign please contact us .");
					}
				}else{
					return builderUX_flash('danger',"Error occurred with connecting to DocuSign please contact us .");
				}
			}
		}else{
			$message=isset($envelop_summary->message) ? $envelop_summary->message : "unable to login";
			$wpdb->update( $wpdb->prefix.'builder_reservation', array('envelope_id'=>$message), array('id'=>$reservation_id));
			return builderUX_flash('danger',"Unable to Create your envelope.Please try again or contact us");
		}
	}
	catch (DocuSign\eSign\ApiException $ex)
	{
		echo "Exception: Error occurred with DocuSign please contact us ." . $ex->getMessage() . "\n";
	}
}
/**
* Funct docusign
*
* param array $postdata 
* @return mixed,
**/
function builderux_docusign_htmlversion($reservation_id)
{
	global $wpdb;
	$option=get_option('builderux_BuyNow_setting');
	$reservation_data=get_reservation_data($reservation_id);
	if(is_lotstatus($reservation_data->lotid)!=true){
		return builderUX_flash('danger',"Your lot is invalid.Please Try again.");
		exit;
	}
	if(!isset($reservation_data->transection_id) && !empty($reservation_data->transection_id)){
  		return builderUX_flash('danger',"you did't deposit the reservation.Please make payment first.");
		die;
	}
	if($reservation_data->gotocontact!='yes'){
		return builderUX_flash('danger',"Your request is invalid.Please Try again.");
		exit;
	}
	set_time_limit(500);
	$docusigndetail=BxGetDocusign($subdivision_data);
	if((!isset($docusigndetail['DSLogin'])) || (!isset($docusigndetail['DSPass'])))
		return builderUX_flash('danger',"Unable to Get Docusign.Please contact admin.");
	//userinfo
	require_once BUILDERUX_DIR_PATH.'/vendor/html2pdf/vendor/autoload.php';
	if(isset($_REQUEST['div_id'])){
		$doc_file_path=BUILDERUX_JSON_UPLOAD.'templates/TopoDocument-'.$_REQUEST['div_id'].'.tpl';
		if(!file_exists($doc_file_path))
			$doc_file_path=BUILDERUX_JSON_UPLOAD.'templates/TopoDocument.tpl';
	}else{
		$doc_file_path=BUILDERUX_JSON_UPLOAD.'templates/TopoDocument.tpl';
	}
	
	//pdf
	$documentFileName = BUILDERUX_JSON_UPLOAD."templates/builderuxContract.pdf";
    //$documentName = "builderuxContract.pdf";
    $doc_data='';
	if(file_exists($doc_file_path)){
		$doc_file_content = fopen($doc_file_path, "r");
		if ($doc_file_content) {
			while (($line = fgets($doc_file_content)) !== false) {
				$doc_data.= $line."<br />";
			}
			fclose($doc_file_content);
		}
	}
	//replace shortcodes
	$lot_datas = get_bxdata_byID('builder_phaselot','ID ',$reservation_data->lotid);
	$lot_promo_price = isset($lot_datas->UserDefinedPreplot1) ? $lot_datas->UserDefinedPreplot1: '';
	$phaseplan_data = get_bxdata_byID('builder_phaseplan','ID ',$lot_datas->PhasePlanID);
	$subdivision_data = get_bxdata_byID('builder_subdivision','ID ',$reservation_data->division_id);
	if(empty($subdivision_data)){
		return builderUX_flash('danger',"Invalid Data.Please try again or contact to admin");
		exit;
	}
	$Lot_City = isset($lot_datas->City) ? $lot_datas->City: '';
	$Lot_County = isset($lot_datas->Country) ? $lot_datas->Country: '';
	$Lot_State = isset($lot_datas->State) ? $lot_datas->State: '';
	$Lot_Address1 = isset($lot_datas->Address1) ? $lot_datas->Address1: '';
	$Lot_Zip = isset($lot_datas->Zip) ? $lot_datas->Zip: '';
	$Lot_Number = isset($lot_datas->JobUnitNum) ? $lot_datas->JobUnitNum: '';
	$Subdivision_Marketing_Name = isset($reservation_data->MarketingName) ? $reservation_data->MarketingName: '';
	$Demo10 = isset($reservation_data->financetype) ? $reservation_data->financetype: '____';
	$co_buyer_first_name = isset($reservation_data->co_buyer_first_name) ? $reservation_data->co_buyer_first_name: ' ';
	$co_buyer_last_name = isset($reservation_data->co_buyer_last_name) ? $reservation_data->co_buyer_last_name: ' ';
	$Demo1 = isset($lot_datas->Demo1) ? $lot_datas->Demo1: '';
	$Demo2 = isset($lot_datas->Demo2) ? $lot_datas->Demo2: '';
	$Broker_Name = isset($lot_datas->Broker_Name) ? $lot_datas->Broker_Name: '';
	$Realtor_License = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$Demo39 = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$HOA_Fees = isset($subdivision_data->HOAFees) ? $subdivision_data->HOAFees: '';
	$Misc_Fee = isset($subdivision_data->MiscellaneousFee) ? $subdivision_data->MiscellaneousFee: '';
	$Setup_Fee = isset($subdivision_data->SetupFee) ? $subdivision_data->SetupFee: '';
	$Broker_Company = isset($lot_datas->company_fields) ? $lot_datas->company_fields: '';
	$company_fields = isset($reservation_data->company_fields) ? $reservation_data->company_fields: '';
	$Realtor_License = isset($lot_datas->Realtor_License) ? $lot_datas->Realtor_License: '';
	$first_name=isset($reservation_data->FirstName) ? $reservation_data->FirstName: '';
	$last_name=isset($reservation_data->LastName) ? $reservation_data->LastName: '';
	$plan_marketing_name = isset($phaseplan_data ->MarketingName ) ? $phaseplan_data ->MarketingName : '';
	$garage_orientation = isset($lot_datas->GarageOrientation) ? $lot_datas->GarageOrientation: '____';
	$Total_Prices = isset($lot_datas->LotTotalPrice) ? $lot_datas->LotTotalPrice: '';
	$discount =!empty($option['discount'])?$option['discount']:'';
	$Discount_price =!empty($discount)?$discount:'NA';
	$color_scheme = isset($lot_datas->ColorScheme) ? $lot_datas->ColorScheme: '_____';
	$Buyer_Address1 = isset($reservation_data->address1) ? $reservation_data->address1: '';
	$Buyer_City = isset($reservation_data->city) ? $reservation_data->city: '';
	$StreetAddress = isset($reservation_data->StreetAddress) ? $reservation_data->StreetAddress: '';
	$BuyerPhone =  isset($reservation_data->Phone) ? $reservation_data->Phone: '';
	$Buyer_State = isset($reservation_data->state) ? $reservation_data->state: '';
	$Buyer_Zip = isset($reservation_data->Buyer_Zip) ? $reservation_data->Buyer_Zip: '';
	$agentEmail = isset($subdivision_data->Email) ? $subdivision_data->Email: '';
	$agentName = empty($subdivision_data->Phone) ?'Sales Agent': $subdivision_data->Phone;
	$co_buyer_email = isset($reservation_data->co_buyer_email_address) ? $reservation_data->co_buyer_email_address: '';
	$Realtor_name = isset($reservation_data->Realtor_name) ? $reservation_data->Realtor_name: '___________________';
	$realtor_email=isset($reservation_data->realtor_email__address) ? $reservation_data->realtor_email__address: '';
	$realtor_license_number = isset($reservation_data->realtor_license_number) ? $reservation_data->realtor_license_number: '___________________';
	$closingDate = isset($lot_datas->LegalAddress1) ? $lot_datas->LegalAddress1: '';
	$Deposits_Paid = isset($option['standard_trans_fee']) ? $option['standard_trans_fee']: '';
	$closing_costs= (isset($reservation_data->closing_cost) && $reservation_data->closing_cost) ? $reservation_data->closing_cost: 0;
	$financetype=$reservation_data->financetype;
	$Total_Prices = empty($lot_promo_price)?$Total_Prices:$lot_promo_price;
	$Total_Price = $Total_Prices+$closing_costs;
	$Balance = $Total_Price-$Deposits_Paid;
	$sellerEmail = (isset($subdivision_data->Service1) && filter_var($subdivision_data->Service1, FILTER_VALIDATE_EMAIL)) ? $subdivision_data->Service1: 'bsink@wadejurneyhomes.com';
	if($Balance>$discount){
	$Balance_price = $Balance-$discount;
	}else{ 
		$Balance_price = $Balance;
	}
	$fhacheckbox=$vacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/unchecked.png'/>";
	$isContracttype=false;
	if($financetype=='FHA'){
		//$fhacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/checked.png'/>";
		$isContracttype=true;
	}elseif($financetype=='VA'){
		//$vacheckbox="<img src='".BUILDERUX_DIR_URL."/assets/img/checked.png'/>";
		$isContracttype=true;
	}
	
	$doc_form_data='';
	// inject the Document Forms
	if(!empty($subdivision_data->LegalName) && (substr($subdivision_data->MarketingName, -2) != '25')){
		$doc_formpath=BUILDERUX_JSON_UPLOAD.'/templates/'.bx_cleanstring($subdivision_data->LegalName).'.tpl';
		
		if(file_exists($doc_formpath)){
			$doc_form_content = fopen($doc_formpath, "r");
			if ($doc_file_content) {
				while (($line = fgets($doc_form_content)) !== false) {
					$doc_form_data.= $line."<br />";
				}
				fclose($doc_form_content);
			}
			//replace the shortcode
			
		}
	}
	$fhavacontent='';
	$vhafaform=BUILDERUX_JSON_UPLOAD.'/templates/fhavaform.tpl';
	if(file_exists($vhafaform) && $isContracttype==true){
		$fhavafile = fopen($vhafaform, "r");
		if ($fhavafile) {
			while (($line = fgets($fhavafile)) !== false) {
				$fhavacontent.= $line."<br />";
			}
			fclose($fhavafile);
		}
	}
	$doc_form_data=$doc_form_data.$fhavacontent;
	if($subdivision_data->State=='NC'){
	$nc_mineralRight='';
	 $mineralrights=BUILDERUX_JSON_UPLOAD.'/templates/nc-mineralrights.tpl';
	if(file_exists($mineralrights)){
		
		$ncfile = fopen($mineralrights, "r");
		if ($ncfile) {
			while (($line = fgets($ncfile)) !== false) {
				$nc_mineralRight.= $line."<br />";
			}
			fclose($ncfile);
		}
	}
	 $doc_form_data=$doc_form_data.$nc_mineralRight;	
	 
	}
	
	 $doc_data=str_replace(array('{{DocumentForms}}','{{va-checkbox}}','{{fha-checkbox}}'),array($doc_form_data,$vacheckbox,$fhacheckbox),$doc_data);
	// inject the forms	
	$documentName = $Subdivision_Marketing_Name.'-'.$Lot_Number.'-'.$first_name;
	$shortcodes=array('{{lot_name}}','{{division}}','{{subdivision}}','{{lotprice}}','{{contract_date}}','{{buyer_name}}','{{co_buyer_first_name}}','{{StreetAddress}}','{{Phone}}','{{Lot_City}}','{{Lot_County}}','{{Lot_State}}','{{Lot_Address1}}','{{Lot_Zip}}','{{Lot_Number}}','{{Subdivision_Marketing_Name}}','{{Demo10}}','{{Demo1}}','{{Broker_Name}}','{{Realtor_License}}','{{Demo39}}','{{HOA_Fees}}','{{Misc_Fee}}','{{Setup_Fee}}','{{Broker_Company}}','{{Realtor_License}}','{{Buyer_Address1}}','{{city}}','{{state}}','{{Buyer_Zip}}','{{plan_marketing_name}}','{{garage_orientation}}','{{color_scheme}}','{{Total_Price}}','{{Deposits_Paid}}','{{Balance_price}}','{{closing_costs}}','{{Demo2}}','{{Realtor_name}}','{{realtor_license_number}}','{{company_fields}}','{{closingDate}}','{{lot_City}}','{{lot_County}}','{{lot_State}}','{{lot_Address}}','{{lot_Zip}}','{{Lot_Number}}','{{Subdivision_Marketing_Name}}','{{HOA_Fees}}','{{Misc_Fee}}','{{Broker_Name}}','{{Realtor_License}}','{{Discount_price}}');
	$values=array(isset($reservation_data->JobUnitNum)?$reservation_data->JobUnitNum:'',isset($reservation_data->division_code)?$reservation_data->division_code:'',isset($reservation_data->code)?$reservation_data->code:'', isset($reservation_data->Cost)?$reservation_data->Cost:'',date("Y-m-d"),$first_name.' '.$last_name,$co_buyer_first_name,$StreetAddress,$BuyerPhone,$Lot_City,$Lot_County,$Lot_State,$Lot_Address1,$Lot_Zip,$Lot_Number,$Subdivision_Marketing_Name,$Demo10,$Demo1,$Broker_Name,$Realtor_License,$Demo39,$HOA_Fees,$Misc_Fee,$Setup_Fee,$Broker_Company,$Realtor_License,$Buyer_Address1,$Buyer_City,$Buyer_State,$Buyer_Zip,$plan_marketing_name,$garage_orientation,$color_scheme,$Total_Price,$Deposits_Paid,$Balance_price,$closing_costs,$Demo2,$Realtor_name,$realtor_license_number,$company_fields,$closingDate,$Lot_City,$Lot_County,$Lot_State,$Lot_Address1,$Lot_Zip,$Lot_Number,$Subdivision_Marketing_Name,$HOA_Fees,$Misc_Fee,$Broker_Name,$Realtor_License,$Discount_price); 
	$recipientEmail=isset($reservation_data->Email) ? $reservation_data->Email: '';
	$first_name=isset($reservation_data->FirstName) ? $reservation_data->FirstName: '';
	$last_name=isset($reservation_data->LastName) ? $reservation_data->LastName: '';
	$recipientName=$first_name.' '.$last_name;
	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($reservation_data->$fieldkey) ? $reservation_data->$fieldkey : '';
		}
	}
	$doc_data=str_replace($shortcodes,$values,$doc_data);
	//replace
	try
	{	
		$html2pdf = new HTML2PDF('P', 'A4', 'fr',true,'UTF-8',array(5, 30, 5, 8));
		// remove default header/footer
		$html2pdf->setTestIsImage(false);
		$html2pdf->setTestTdInOnePage(false);
		$html2pdf->writeHTML($doc_data);
		$html2pdf->Output($documentFileName,'f');
	}
	catch(HTML2PDF_exception $e) 
	{
		print_r($e) ;
		exit;
	}
    // configure the document we want signed
    //generate pdf
	$username = $docusigndetail['DSLogin'];
	$password =$docusigndetail['DSPass'];
    $integrator_key = $option['docu_integrator_key'];
    $host = $option['docu_host'];
   	$templateid= get_docusign_templateid($reservation_data->division_id);
    // create a new DocuSign configuration and assign host and header(s)
    $config = new DocuSign\eSign\Configuration();
    $config->setHost($host);
    $config->addDefaultHeader("X-DocuSign-Authentication", "{\"Username\":\"" . $username . "\",\"Password\":\"" . $password . "\",\"IntegratorKey\":\"" . $integrator_key . "\"}");
    /////////////////////////////////////////////////////////////////////////
    // STEP 1:  Login() API
    /////////////////////////////////////////////////////////////////////////
    // instantiate a new docusign api client
    $apiClient = new DocuSign\eSign\ApiClient($config);    
    try 
	{
		//*** STEP 1 - Login API: get first Account ID and baseURL
		$authenticationApi = new DocuSign\eSign\Api\AuthenticationApi($apiClient);
		$options = new \DocuSign\eSign\Api\AuthenticationApi\LoginOptions();
		$loginInformation = $authenticationApi->login($options);
		if(!isset($loginInformation->errorCode) && count($loginInformation) > 0)
		{
			$loginAccount = $loginInformation->getLoginAccounts()[0];
			$host = $loginAccount->getBaseUrl();
			$host = explode("/v2",$host);
			$host = $host[0];
			// UPDATE configuration object
			$config->setHost($host);
			// instantiate a NEW docusign api client (that has the correct baseUrl/host)
			$apiClient = new DocuSign\eSign\ApiClient($config);
			if(isset($loginInformation))
			{
				$accountId = $loginAccount->getAccountId();
				if(!empty($accountId))
				{
					//*** STEP 2 - Signature Request from a Template
					// create envelope call is available in the EnvelopesApi
					$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);
					
					// Add a document to the envelope
					$document = new DocuSign\eSign\Model\Document();
					$document->setDocumentBase64(base64_encode(file_get_contents($documentFileName)));
					$document->setName($documentName);
					$document->setDocumentId("1");
					// assign recipient to template role by setting name, email, and role name.  Note that the
					// template role name must match the placeholder role name saved in your account template.
					$templateRole = new  DocuSign\eSign\Model\TemplateRole();
					$templateRole->setEmail($recipientEmail);
					$templateRole->setName($recipientName);
					$templateRole->setRoleName("Buyer");            
					$templateRole->setClientUserId('12345');
									
					$templateRole1 = new  DocuSign\eSign\Model\TemplateRole();
					$templateRole1->setEmail($agentEmail);
					$templateRole1->setName($agentName);
					$templateRole1->setRoleName("SA");             
					//$templateRole1->setClientUserId('12345');
					$all_template_roles = array($templateRole,$templateRole1);
					if(!empty($co_buyer_email)){
						$templateRole3 = new  DocuSign\eSign\Model\TemplateRole();
						$templateRole3->setEmail($co_buyer_email);
						$templateRole3->setName($co_buyer_first_name.' '.$co_buyer_last_name);
						$templateRole3->setRoleName("co-buyer");             
						//$templateRole3->setClientUserId('12345');
						$all_template_roles[]=$templateRole3;
					}
					if(!empty($realtor_email)){
						$realtorrole = new  DocuSign\eSign\Model\TemplateRole();
						$realtorrole->setEmail($realtor_email);
						$realtorrole->setName($Realtor_name);
						$realtorrole->setRoleName("Realtor");             
						//$templateRole3->setClientUserId('12345');
						$all_template_roles[]=$realtorrole;
					}
					
					// Send last email to Seller 
					$Seller_contract = new  DocuSign\eSign\Model\TemplateRole();
					//$Seller_contract->setEmail('contract@wadejurneyhomes.com');
					$Seller_contract->setEmail($sellerEmail);
					$Seller_contract->setName('Wade jurney');
					$Seller_contract->setRoleName("Seller");             
					//$templateRole3->setClientUserId('12345');
					$all_template_roles[]=$Seller_contract;
					// instantiate a new envelope object and configure settings
					$envelop_definition = new DocuSign\eSign\Model\EnvelopeDefinition();
					$subname=isset($subdivision_data->MarketingName) ? $subdivision_data->MarketingName : '';
					$lotjobnum=isset($lot_datas->JobUnitNum) ? $lot_datas->JobUnitNum : '';
					$envelop_definition->setEmailSubject(" E-CONTRACT – {$subname} – {$lotjobnum}");
					$envelop_definition->setTemplateId($templateid);
					$envelop_definition->setDocuments(array($document));
					$envelop_definition->setTemplateRoles($all_template_roles);
					
					// set envelope status to "sent" to immediately send the signature request
					$envelop_definition->setStatus("sent");
					// optional envelope parameters
					$options = new \DocuSign\eSign\Api\EnvelopesApi\CreateEnvelopeOptions();
					$options->setCdseMode(null);
					$options->setMergeRolesOnDraft(null);
					// create and send the envelope (aka signature request)
					$envelop_summary = $envelopeApi->createEnvelope($accountId, $envelop_definition, $options);
					if(!isset($envelop_summary->errorCode)){
						$document=json_decode($envelop_summary);
						$envloped=$document->envelopeId;
						if((isset($_GET['test']) && $_GET['test']==true)) {
							if((isset($_GET['SubmitLead']) && $_GET['SubmitLead']==true)) {
								// get the contract agreement url
								$zipfile=BX_Getdocusign_pdf($document->envelopeId,$accountId,$apiClient);
								// lets submit the lead
								submit_ss_lead($reservation_data,$zipfile); // submit the lead to the sales simplicity
								$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$formprocess,'_envelopid'=>$envloped,'test'=>'true','SubmitLead'=>'true'),get_permalink());
								//update into local DB
								$wpdb->update( $wpdb->prefix.'builder_phaselot', array('StatusName'=>'Sold'), array('id'=>$reservation_data->lotid));
							}else{
								$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$reservation_data->id),get_permalink());
							}
						}else{
							// get the contract agreement url
							$zipfile=BX_Getdocusign_pdf($document->envelopeId,$accountId,$apiClient);
							// lets submit the lead
							submit_ss_lead($reservation_data,$zipfile); // submit the lead to the sales simplicity
							$ReturnUrl=add_query_arg(array('action'=>'docusign_request','_uid'=>$formprocess,'_envelopid'=>$envloped),get_permalink());
							//update into local DB
							$wpdb->update( $wpdb->prefix.'builder_phaselot', array('StatusName'=>'Sold'), array('id'=>$reservation_data->lotid));
						}
						//update the envelopid in the table
						global $wpdb; 
						$wpdb->update( $wpdb->prefix.'builder_reservation', array('ReservationStatus'=>"Docusign Done",'envelope_id'=>$document->envelopeId), array('id'=>$reservation_id));
						$viewrequest = new DocuSign\eSign\Model\RecipientViewRequest();
						$viewrequest->setUserName($recipientName); 
						$viewrequest->setEmail($recipientEmail);
						$viewrequest->setAuthenticationMethod('email');
						$viewrequest->setClientUserId('12345');
						$viewrequest->setReturnUrl($ReturnUrl);
						$envelopview=$envelopeApi->createRecipientView($accountId,$document->envelopeId,$viewrequest);
						$redirecturl=$envelopview->getUrl();
						$emaillink=add_query_arg(array('EnvelopID'=>$document->envelopeId,'_uid'=>$formprocess,'action'=>"DocusignSignature",'__U'=>$recipientName,'__E'=>$recipientEmail,'ReturnUrl'=>urlencode($ReturnUrl)),get_permalink());
						bx_Send_Docusign_email($recipientEmail,$redirecturl,$emaillink);
					}else{
						$message=isset($envelop_summary->message) ? $envelop_summary->message : "unable to create envelope";
						$wpdb->update( $wpdb->prefix.'builder_reservation', array('envelope_id'=>$message), array('id'=>$reservation_id));
						return builderUX_flash('danger',"Unable to Create your envelope.Please try again or contact us");
					}
				}else{
					return builderUX_flash('danger',"Unable to process your request.");
				}
			}
		}else{
			$message=isset($envelop_summary->message) ? $envelop_summary->message : "unable to login";
			$wpdb->update( $wpdb->prefix.'builder_reservation', array('envelope_id'=>$message), array('id'=>$reservation_id));
			return builderUX_flash('danger',"Unable to Create your envelope.Please try again or contact us");
		}
	}
	catch (DocuSign\eSign\ApiException $ex)
	{
		echo "Exception: " . $ex->getMessage() . "\n";
	}
}
function bx_Send_Docusign_email($to,$redirecturl,$emaillink){
	if(!function_exists('wp_mail')) {
			include(ABSPATH . "wp-load.php"); 
			include(ABSPATH . "wp-includes/pluggable.php"); 
	}
	$shortcodes=array('{{documenturl}}');
	$values=array(isset($emaillink)?$emaillink:''); 
	
	$form_id='docusign';
		
	$all_templates_byid = get_email_template($form_id);
	$doc_data=stripslashes($all_templates_byid->email_body);
	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($reservation_data->$fieldkey) ? $reservation_data->$fieldkey : '';
		}
	}
	$doc_data=str_replace($shortcodes,$values,$doc_data);
	$mailhtml=stripslashes($doc_data);	
	
	$subject=$all_templates_byid->subject;
	add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
	function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}
	add_filter('wp_mail_from','bx_wp_mail_from');
	function bx_wp_mail_from($content_type) {
		$admin_email = isset($all_templates_byid->to_email)? $all_templates_byid->to_email: get_bloginfo( 'admin_email' );
		return $admin_email;
	}
	add_filter('wp_mail_from_name','bx_wp_mail_from_name');
	function bx_wp_mail_from_name($name) {
		$site_title = isset($all_templates_byid->from_name)? $all_templates_byid->from_name: get_bloginfo( 'name' );
	  return $site_title;
	}
	$headers[] = 'Cc: <hc@builderux.com>';
	wp_mail( $to, $subject, $mailhtml, $headers);
	// Reset content-type to avoid conflicts -- https://core.trac.wordpress.org/ticket/23578
	remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );	
	wp_redirect($redirecturl);	
}
/*
 * 
 * name: bx_CheckLotstatusInSS
 * @param int $lotid
 * @return boolean
 * 
 */
function bx_CheckLotstatusInSS($lotid){
	if(isset($_GET['test'])) {
		return true;
	}
	$options=get_option('builderux_BuyNow_setting');
	$wsdl=isset($options['topo_ss_wsdl']) ? $options['topo_ss_wsdl'] : '';
	$sGUID=isset($options['topo_ss_guid']) ? $options['topo_ss_guid'] : '';
	if(!$wsdl || !$sGUID){
		echo builderUX_flash('danger',"Error to get data from SS.Please contact admin.");
	}else{
		try {
			$client = new SoapClient($wsdl);
			$result = $client->GetSpecLotInfoStyle1(array(
				'sGUID' => $sGUID,
			));
		}
		catch (Exception $e) {
			echo builderUX_flash('danger',$e->getMessage());
			die;
		}
		if(!empty($result) && isset($result->GetSpecLotInfoStyle1Result)){
			$xmlcontent=$result->GetSpecLotInfoStyle1Result;
			$xmlcontent=preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $xmlcontent);
			$xml = simplexml_load_string($xmlcontent, "SimpleXMLElement", LIBXML_NOCDATA);
			
			$json = json_encode($xml);
			$SpecLots = json_decode($json,TRUE); 
			// if not empty response	
			if(!empty($SpecLots) && isset($SpecLots['Lot']) && !empty($SpecLots['Lot'])){
				// return true if lot is still spec
				$lots=$SpecLots['Lot'];
				foreach($lots as $lot){
					if(isset($lot['ID']) && $lot['ID']==$lotid)
						return true; // lot is still spec
				}
			}
		}
		return false; // lot is not spec	
	}
}
/*
 * 
 * name: BX_Getdocusign_pdf
 * @param
 * @return
 * 
 */
function BX_Getdocusign_pdf($envlopid,$accountId,$apiClient){
	
	if(!$envlopid)
		return false;
	
    if(empty($accountId))
	{
		return false;
	}	
	$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);	
	$docs=$envelopeApi->listDocuments($accountId,$envlopid);
	$documents=$docs->getEnvelopeDocuments();
	foreach($documents as $key=>$document){
		$doctype=$document->getType();
		if($doctype=='content'){
			$docid=$document->getDocumentId();
			$doc=$envelopeApi->getDocument($accountId,$envlopid,$docid);
			$pdfpath=$doc->getPathName();
			if($pdfpath){
				$filename=$doc->getFileName();
				$aggree_dir=BUILDERUX_JSON_UPLOAD.'/agreements/';
				if(!is_dir($aggree_dir))
					mkdir($aggree_dir);
				$filename='agreement_'.time().'.pdf';	
				$realpdfpath=$aggree_dir.$filename;	
				if (copy($pdfpath,$realpdfpath)) {
					return BUILDERUX_JSON_UPLOAD_URL.'/agreements/'.$filename;
				}
			}
		}
	}
	
}
/*
 * 
 * name: docusign return 
 * @param
 * @return redirect
 * 
 */
function handle_docusign_return()
{
	$event=$_GET['event'];
	switch ($event)
	{
		//successfull
		case 'signing_complete':
			global $wpdb;
			
			$reservationid=isset($_GET['_uid']) ? $_GET['_uid'] : '';
			$envelopid=isset($_GET['_envelopid']) ? $_GET['_envelopid'] : '';
			$reservation_data=get_reservation_data($reservationid);
			if($reservation_data){
				// get the contract agreement url
				//$zipfile=BX_Getdocusign_pdf($envelopid,$reservation_data);
				// lets submit the lead
				//submit_ss_lead($reservation_data,$zipfile); // submit the lead to the sales simplicity
				BX_submit_Stat($reservation_data);
				// lets execute background process to ignore timeout
				/*$file=__DIR__ .'/BuilderUxCommandFunction.php';
				$cmd="php -r \"require '$file'; RunCommandFunction($reservationid,'$envelopid');\"";
				$pipe = popen($cmd, 'r');
				if (empty($pipe)) {
					throw new Exception("Unable to open pipe for command '$cmd'");
				}

				stream_set_blocking($pipe, false);
				while (!feof($pipe)) {
					$output_submit=fread($pipe, 1024);
					print_r($output_submit);
					sleep(1);
					flush();
				}
				
				pclose($pipe);
				* */ 
  
				
				//update reservation status
				$wpdb->update( $wpdb->prefix.'builder_reservation', array('ReservationStatus'=>"Completed"), array('id'=>$reservation_data->id));
				$redirect=add_query_arg(array('action'=>'thanks','_uid'=>$reservationid,'event'=>$event,'usertype'=>'prequali'),get_permalink());
				wp_redirect($redirect);
				/*echo $script='<script> 
					var count = 5;
					  var countdown = setInterval(function(){
						jQuery("#timer").html(count + " sec");
						if (count == 0) {
						  clearInterval(countdown);
						  window.location="'.$redirect.'"
						 }
						count--;
					  }, 1000);
					</script>';		
				return builderUX_flash('success',"Request successful! Redirecting you in <b id='timer'>5 seconds</b>.<a href='{$redirect}'>Click Here</a> if you are not redirected automatically.");
				* */
			}else{
				return builderUX_flash('danger',"Your reservation is invalid.Please try again.");
			}
		break;
		//canceld
		case 'cancel':
			return builderUX_flash('danger',"you have canceled the DocuSign request.your reservation can't completed without document.");
			break;
		//decliend	
		case 'decline':
			return builderUX_flash('danger',"you have declined the DocuSign request.your reservation can't completed without document.");
			break;
		// session timeouit	
		case 'session_timeout':
			return builderUX_flash('danger',"Your signing session has been expired . Please try again.");
			break;
		//a processing error occurs during the signing session	
		case 'exception':
			return builderUX_flash('danger',"something went wrong please try again.");
		break;
		case 'ttl_expired':
			return builderUX_flash('danger',"the token was not used within the timeout period or the token was already accessed.");
		break;
		default :
			return builderUX_flash('danger',"your request could not processed . Please try again.");
		break;
	}
	//return true;	
}
/*
 * 
 * name: stripe_payment_process
 * @param array
 * @return string
 * 
 */
function stripe_payment_process($postrequest,$option,$reservation_data)
{
	if($reservation_data)
	{
		$stripe_mode=$option['stripe_mode'];
		if(($stripe_mode=='testing') || ((isset($_GET['test']) && $_GET['test']==true))){
			$sripe_key=$option['stripe_test_secret'];
			$paymentmode='testing';
		}
		elseif($stripe_mode=='live'){
			$paymentmode='live';
			$sripe_key=$option['stripe_live_secret'];
		}
		else{
			return 	builderUX_flash('danger','no payment method configured.');
		}
		if(is_lotstatus($reservation_data->lotid)!=true){
		return builderUX_flash('danger',"Your lot is invalid.Please Try again.");
		exit;
		}
		if($reservation_data->gotocontact=='yes'){	
			$phase_lot_datas = get_bxdata_byID('builder_phaselot','ID ',$reservation_data->lot_id);
			$phaseplan_data_datas = get_bxdata_byID('builder_phaseplan','ID ',$phase_lot_datas->PhasePlanID);
			$ammmount_to_charged=intval($option['standard_trans_fee']*100);	
			$Lot_number = $reservation_data->JobUnitNum;
			$Community  = preg_replace('/\W\w+\s*(\W*)$/', '$1', $reservation_data->MarketingName);
			$BuyerName  = isset($reservation_data->FirstName)?$reservation_data->FirstName:'';
			$BuyerName.= isset($reservation_data->LastName)?$reservation_data->LastName:'';
		
			\Stripe\Stripe::setApiKey($sripe_key);
			$curl = new \Stripe\HttpClient\CurlClient(array(CURLOPT_SSL_VERIFYPEER => false));
			// tell Stripe to use the tweaked client
			\Stripe\ApiRequestor::setHttpClient($curl);
			  try {
				if (empty($postrequest['street']) || empty($postrequest['city']) || empty($postrequest['zip']))
				  throw new Exception("Fill out all required fields.");
				if (!isset($postrequest['stripeToken']))
				  throw new Exception("The Stripe Token was not generated correctly");
				  
				if($postrequest['payment_type']=='card')  
				{
					$payment=\Stripe\Charge::create(array("amount" =>$ammmount_to_charged,
											"currency" => $option['currency'],
											"card" => $postrequest['stripeToken'],
											"metadata" => array("Community" => $Community,"Lot_number"=>$Lot_number),
											"description" => $postrequest['email']));
											
				}elseif($postrequest['payment_type']=='bank'){
					$token_id=$_POST['stripeToken'];
					$customer = \Stripe\Customer::create(array(
					  "source" => $token_id,
					  "description" => "TopoBuy Now customer")
					);	
					$customer=builderux_accessProtected($customer,'_values');
					if($customer)
					{
						// get the existing bank account
						$customer_retrived = \Stripe\Customer::retrieve($customer['id']);
						$bank_account = $customer_retrived->sources->retrieve($customer_retrived['default_source']);
						// verify the account
						$bank_account->verify(array('amounts' => array(32, 45)));
						$customer_values=builderux_accessProtected($customer_retrived,'_values');
						//charge the customer
						$payment=\Stripe\Charge::create(array(
						  "amount"   => $ammmount_to_charged,
						  "currency" => $option['currency'],
						  "metadata" => array("Community" => $Community,"Lot_number"=>$Lot_number),
						  "customer" => $customer_values['id'] // Previously stored, then retrieved
						  ));
					}
				}
				if($payment)
				{
					global $wpdb; 
					//update reservation
					$wpdb->update( $wpdb->prefix.'builder_reservation', array('ReservationStatus'=>"Payment Done",'payment_mode'=>$paymentmode,'transection_id'=>$payment->id,'transection_logs'=>$payment), array('id'=>$reservation_data->id));
					$reservation_datas = array('TOPObuy'=>$reservation_data);
					//echo "<pre>"; print_r($reservation_data); echo "</pre>";
					add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type2' );
					function wpdocs_set_html_mail_content_type2() {
						return 'text/html';
					}
					add_filter('wp_mail_from','yoursite_wp_mail_from');
					function yoursite_wp_mail_from($content_type) {
						$admin_email = get_bloginfo( 'admin_email' );
					  return $admin_email;
					}
					add_filter('wp_mail_from_name','yoursite_wp_mail_from_name');
					function yoursite_wp_mail_from_name($name) {
						$site_title = get_bloginfo( 'name' );
					  return $site_title;
					}
					$to = $reservation_data->Email;
					if(isset($reservation_data->SubdivisionEmail))
						$to.=",".$reservation_data->SubdivisionEmail;
					//$to = "keshvender.glocify@gmail.com";
					$form_id="email_reciept";
					$recieptemail = get_email_template($form_id);
					$subject = isset($recieptemail->subject) ? $recieptemail->subject: '';
					$recieptcontent = isset($recieptemail->email_body) ? $recieptemail->email_body: '';
					$ammmount_to_charged = $ammmount_to_charged/100;
					$floor_plan=isset($phaseplan_data_datas->MarketingName) ? $phaseplan_data_datas->MarketingName : '';
					$mail_shortcodes=array('{{community}}','{{lot}}','{{price}}','{{floor_plan}}','{{BuyerName}}');
					$mail_values=array($Community,$Lot_number,'$'.$ammmount_to_charged,$floor_plan,$BuyerName); 
					$doc_data=str_replace($mail_shortcodes,$mail_values,$recieptcontent);
					wp_mail( $to, $subject, $doc_data);
					// Reset content-type to avoid conflicts -- https://core.trac.wordpress.org/ticket/23578
					remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type2' );
					//send email to studio
					BuxSendSubdivisionEmail($reservation_data->id);
					if(isset($option['Docusign_Contract_type']) && $option['Docusign_Contract_type']=='pdf')
						return builderux_docusign_pdfversion($reservation_data->id);
					else		
						return $document=builderux_docusign_htmlversion($reservation_data->id);
				}							
								
			}catch (Exception $e) {
				 return builderUX_flash('danger',$e->getMessage());
			}
		}else{
			$html.=builderUX_flash('danger',"Your request is invalid.Please Try again.");
		}	
	}else{
		return builderUX_flash('danger','invalid reservation !Please try again.');
	}	
}
/*
 * 
 * name: builderux_accessProtected
 * @param obj string
 * @return mixed
 * 
 */
function builderux_accessProtected($obj, $prop) {
  $reflection = new ReflectionClass($obj);
  $property = $reflection->getProperty($prop);
  $property->setAccessible(true);
  return $property->getValue($obj);
}
/**
* Funct send lender email
*
* param array $postdata 
* @return bolean,
**/
function send_lender_email($postdata,$reservation_data)
{
	global $wpdb;
	$lenders=$postdata['TOPObuy']['lenders'];
	$prefix=$wpdb->prefix;
	add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));	
			
	add_filter( 'wp_mail_from_name', function( $name ) {
		return get_bloginfo('name');
	});
	add_filter( 'wp_mail_from', function( $name ) {
		return get_bloginfo('admin_email');
	});
	$clientinfo=$reservation_data;
		
	$form_id='landers';
	$all_templates_byid = get_email_template($form_id);

	$doc_data=$all_templates_byid->email_body;


	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($clientinfo->$fieldkey) ? $clientinfo->$fieldkey : '';
		}
	}


	$doc_data=str_replace($shortcodes,$values,$doc_data);
	 $message=stripslashes($doc_data);	
	
	  $subject=$all_templates_byid->subject;
	  $Cc_email=$all_templates_byid->to_email;
	  	$currentDomain = preg_replace('/www\./i', '', $_SERVER['SERVER_NAME']); 
 		$admin_email = 'admin@'.$currentDomain;
		
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		 
		// Create email headers
		$headers .= 'From: '.$admin_email ."\r\n".
			'Reply-To: '.$admin_email ."\r\n" .
			'Cc: '.$Cc_email ."\r\n" .
			'X-Mailer: PHP/' . phpversion();

		
	if(!empty($lenders))
	{
		
		$lenders = json_decode($lenders,TRUE);
		$success=array();
		foreach($lenders as $key=>$lender)
		{
				
		 	$lenderinfo=$wpdb->get_row("select * from {$prefix}builder_lenders where id={$lender}");
			
			if(!empty($lenderinfo))
			{

 			
				  $emailid=$lenderinfo->Email;
					
				$email=wp_mail($emailid, $subject, $message,$headers);
				$success[]=$email;
			}
		}
	}
	return true;
}

function BuxSendSubdivisionEmail($reservationid){
	$reservation_data=get_reservation_data($reservationid);
	$subdivision=isset($reservation_data->division_id) ? $reservation_data->division_id : '';
	$quilified_status=isset($reservation_data->gotocontact) ? $reservation_data->gotocontact : '';
	$subdivisiondata=get_bxdata_byID('builder_subdivision','ID',$subdivision);
	if(!empty($reservation_data->lenders)){  
		$lenders=unserialize($reservation_data->lenders);
		foreach ($lenders as  $lender) {
			global $wpdb;
			$prefix=$wpdb->prefix;
			$lenderinfos[]=$wpdb->get_row("select CompanyName,Address1,Country from {$prefix}builder_lenders where id={$lender}");
		}
		$lender_data="<table>
		<tr>
		<th>Company</th>
		<th>Contact</th>
		<th>Country</th>
		</tr>";

		foreach ($lenderinfos as $lenderinfo) {
			$lender_data.="<tr>
			<td>".$lenderinfo->CompanyName."</td>
			<td>".$lenderinfo->Address1."</td>
			<td>".$lenderinfo->Country."</td>
			</tr>";
		} 
	}
	$lender_data=isset($lender_data) ? $lender_data : '';
	$shortcodes=array('{{MarketingName}}','{{prequal}}','{{prequal_id}}','{{selected_lenders}}','{{JobUnitNum}}');
	$values=array(
				isset($reservation_data->MarketingName)?$reservation_data->MarketingName:'',
				(!empty($reservation_data->prequal))?$reservation_data->prequal:'#',
				(!empty($reservation_data->prequal_id))?$reservation_data->prequal_id:'#',
				$lender_data,
				isset($reservation_data->JobUnitNum)?$reservation_data->JobUnitNum:'',
				); 	
	if($quilified_status=='yes')
		$form_id='subdivision';
	else
		$form_id='notprequalified';
		
	$all_templates_byid = get_email_template($form_id);
	$doc_data=stripslashes($all_templates_byid->email_body);
	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($reservation_data->$fieldkey) ? $reservation_data->$fieldkey : '';
		}
	}
	$doc_data=str_replace($shortcodes,$values,$doc_data);
	$mailhtml=stripslashes($doc_data);	
	$subject=$all_templates_byid->subject;
	$Cc_email=$all_templates_byid->to_email;
	
	if($subdivisiondata){
		$subdivname=isset($subdivisiondata->MarketingName) ? $subdivisiondata->MarketingName: '';
		$prequal=isset($reservation_data->prequal) ? site_url().$reservation_data->prequal : '';
		$prequalid=isset($reservation_data->prequal_id) ? site_url().$reservation_data->prequal_id : '';
		$emailaddress=$subdivisiondata->Email;
		
		$attachments=array($prequal,$prequalid);
		$admin_email = get_option('admin_email');
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		 
		// Create email headers
		$headers .= 'From: '.$admin_email ."\r\n".
			'Reply-To: '.$admin_email ."\r\n" .
			'Cc: '.$Cc_email ."\r\n" .
			'X-Mailer: PHP/' . phpversion();
		 
		// Compose a simple HTML email message
		// Compose a simple HTML email message
		$preq_file=basename($reservation_data->prequal);
		$preq_id=basename($reservation_data->prequal_id);
		$attachments = array();
		$certdir=BUILDERUX_JSON_UPLOAD.'/certificates/';
		if(!is_dir($certdir)){
			mkdir($certdir);
			$myfile = fopen($up.".htaccess", "w");
    	    fwrite($myfile, "deny from all");
    	    fclose($myfile);
		}
		if(!empty($preq_file)){
		    $ext=pathinfo($preq_file, PATHINFO_EXTENSION);
		    $tmp_preq="{$certdir}Prequalification_Letter_{$reservation_data->id}.{$ext}";
		    copy("{$certdir}$preq_file",$tmp_preq);
		    $attachments[]= $tmp_preq;
		}
		if(!empty($preq_id)){
		    $ext=pathinfo($preq_id, PATHINFO_EXTENSION);
		    $tmp_preq2="{$certdir}Prequalification_ID_{$reservation_data->id}.{$ext}";
		    copy("{$certdir}$preq_id",$tmp_preq2);
		    $attachments[]= $tmp_preq2;
		}
		$ismailsent=wp_mail($emailaddress,$subject,$mailhtml,$headers,$attachments);
		
		unlink($tmp_preq);
		unlink($tmp_preq2);
	}	
	
}
/**
* Funct submit ss lead
*
* param array $postdata 
* @return bolean,
**/
function submit_ss_lead($reservation_data,$zipfile='',$gotocontract=True)
{
	$options=get_option('builderux_BuyNow_setting');
	$wsdl=isset($options['topo_ss_wsdl']) ? $options['topo_ss_wsdl'] : '';
	$sGUID=isset($options['topo_ss_guid']) ? $options['topo_ss_guid'] : '';
	$availablefields=get_BuyNow_fields();
	$daynamicxmlfields="";
	foreach($availablefields as $fields){
		$fieldname=$fields->field_name;
		if(isset($reservation_data->$fieldname) && !empty($reservation_data->$fieldname))
		{
			$string = str_replace('_','',mb_convert_case(mb_strtolower($fieldname), MB_CASE_TITLE, "UTF-8"));
			$daynamicxmlfields.=" {$string}={$reservation_data->$fieldname}";
		}
	}
	$buildername=isset($options['topo_ss_builder']) ? $options['topo_ss_builder'] : '';
	try{
		$fname=isset($reservation_data->FirstName) ? $reservation_data->FirstName : '';
		$lname=isset($reservation_data->LastName) ? $reservation_data->LastName : '';
		$gotocontract1=isset($reservation_data->gotocontact) ? $reservation_data->gotocontact : '';
		$email=isset($reservation_data->Email) ?  $reservation_data->Email :'';
		$JobUnitNum=isset($reservation_data->JobUnitNum) ? $reservation_data->JobUnitNum : '';
		$subdivisionid=isset($reservation_data->division_id) ? $reservation_data->division_id : '';
		$phasename=isset($reservation_data->PhaseName) ? $reservation_data->PhaseName : '';
		$PlanModelNum=isset($reservation_data->PlanModelNum) ? $reservation_data->PlanModelNum : '';
		$Phone=isset($reservation_data->Phone) ? $reservation_data->Phone : '';
		$StreetAddress=isset($reservation_data->StreetAddress) ? $reservation_data->StreetAddress : '';
		$city=isset($reservation_data->city) ? $reservation_data->city : '';
		$state=isset($reservation_data->state) ? $reservation_data->state : '';
		$MarketingName=isset($reservation_data->MarketingName) ? $reservation_data->MarketingName : '';
		$date=date('m/d/Y h:i:s A');
		//cobyer fields
		$co_buyer_middle_initial=isset($reservation_data->co_buyer_middle_initial) ? $reservation_data->co_buyer_middle_initial : '';
		$co_buyer_last_name=isset($reservation_data->co_buyer_last_name) ? $reservation_data->co_buyer_last_name : '';
		$co_buyer_email_address=isset($reservation_data->co_buyer_email_address) ? $reservation_data->co_buyer_email_address : '';
		$co_buyer_address=isset($reservation_data->co_buyer_address) ? $reservation_data->co_buyer_address : '';
		$co_buyer_phone_no=isset($reservation_data->co_buyer_phone_no) ? $reservation_data->co_buyer_phone_no : '';
		$co_buyer_suffix=isset($reservation_data->co_buyer_suffix) ? $reservation_data->co_buyer_suffix : '';
		$co_buyer_first_name=isset($reservation_data->co_buyer_first_name) ? $reservation_data->co_buyer_first_name : '';
		$documenturl=''; //$gototext='';
		if($gotocontract1=='yes'){   
			 $gototext="<GoToContract>True</GoToContract>";
		}else{
			 $gototext="<GoToContract>False</GoToContract>";
		}
		
	 	$xml='<?xml version="1.0" encoding="utf-8"?>
			<RootNode>
			<Lead>
				<Contact FirstName="'.$fname.'" LastName="'.$lname.'" Email="'.$email.'" Phone="'.$Phone.'" StreetAddress="'.$StreetAddress.'" StreetAddress2="" City="'.$city.'" State="'.$state.'" Country="" PostalCode="" VisitDate="'.$date.'" ContactType=""  SendResponse="NO" SendToSalesAgent="NO" IsWebLead="" IPAddress="" WorkPhone="" WorkPhoneExt="" MobilePhone="" Fax="" Pager="" Title="" UserName="" Rank="" TwitterID="" TwitterName="" AllowBeBacks="" ProcessAllScenarios="" UpdateAllDemographics="" DontUpdateBeBackDemos="" ValidateByEmail="" SitesDefaultAddress="" CoFirstName="'.$co_buyer_first_name.'" CoTitle="" CoLastName="'.$co_buyer_last_name.'" CoEmail="'.$co_buyer_email_address.'" CoPhone="'.$co_buyer_phone_no.'" CoWorkPhone="" CoFax="" CoPager="" CoMobilePhone="" CoStreetAddress="'.$co_buyer_address.'" CoStreetAddress2="" CoCity="" CoState="" CoPostalCode="" CoCountry=""/>
			<Qualifications Comments="" Note="" />
				<PropertyInterest BuilderName="'.$buildername.'" MasterCommunity="" CommunityNumber="" CommunityName="'.$MarketingName.'" PlanNumber="" SpecNumber="" SpecAddress="" />
			<Selections>
			<Lot>
			'.$gototext.'
			<JobUnitNum>'.$JobUnitNum.'</JobUnitNum>
			<Phase>'.$phasename.'</Phase>
			<PlanModelNum>'.$PlanModelNum.'</PlanModelNum>
			<SubdivisionID>'.$subdivisionid.'</SubdivisionID>
			</Lot>
			<ContractDocumentURL>'.$zipfile.'</ContractDocumentURL>
			<ContractDocumentDescription>BuilderUX Contract '.$date.'</ContractDocumentDescription>
			<HasContractAttachment>true</HasContractAttachment>
			</Selections>
			</Lead>
			</RootNode>
			';
		$xml=str_replace(array("&","'","'s","'"),array("&#38;","&#145;","&quot;","&#146;"),$xml);	
		try{
			$options = array(
				'soap_version'=>SOAP_1_2,
				'exceptions'=>true,
				'trace'=>1,
				'cache_wsdl'=>WSDL_CACHE_NONE
			);
			$client = new SoapClient($wsdl);
			$result = $client->SubmitEleadXML(array(
				'sGUID' => $sGUID,
				'xml' => $xml
			));
			if($doc = @simplexml_load_string($result->SubmitEleadXMLResult)){
				$xml = simplexml_load_string($result->SubmitEleadXMLResult, "SimpleXMLElement", LIBXML_NOCDATA);
				$json = json_encode($xml); 
				$array = json_decode($json,TRUE);
				//echo "<pre>"; print_r($array); 
				$customerid=isset($array['Lead']['Contact']['@attributes']['CustomerId']) ? $array['Lead']['Contact']['@attributes']['CustomerId'] : false; 
				//updatet the logs
				global $wpdb; 
				$wpdb->update( $wpdb->prefix.'builder_reservation', array('ReservationStatus'=>"Lead Sent",'SSxClientId'=>$customerid,'SSxlogs'=>$json), array('id'=>$reservation_data->id));
				//submit_ss_lead_email($reservation_data,$customerid); 
				return true;
			}else{
				global $wpdb; 
				$wpdb->update( $wpdb->prefix.'builder_reservation', array('SSxlogs'=>$result->SubmitEleadXMLResult), array('id'=>$reservation_data->id));
				//send error log
				BuilderuxErrorLog("Lead Exception On wadejurneyhomes",$result->SubmitEleadXMLResult);	
			}
		}catch(Exception $e){
			return builderUX_flash('danger',"Error to submit the lead. Error: {$message}");
			
		}
		return builderUX_flash('success',"you have submitted your request successfully");
	}catch(Exception $e){
		return builderUX_flash('danger',"Some error to submit your request.".$e->getMessage());
	}
	//get param
	return false;
	
}


/*
 * 
 * name: Elead Email
 * @param
 * @return string
 * 
 */

	function submit_ss_lead_email($reservation_data,$customer_id)
{

	
	$subdivision=isset($reservation_data->division_id) ? $reservation_data->division_id : '';
	$quilified_status=isset($reservation_data->gotocontact) ? $reservation_data->gotocontact : '';
	$subdivisiondata=get_bxdata_byID('builder_subdivision','ID',$subdivision);
	if(!empty($reservation_data->lenders)){  
		$lenders=unserialize($reservation_data->lenders);
		foreach ($lenders as  $lender) {
			global $wpdb;
			$prefix=$wpdb->prefix;
			$lenderinfos[]=$wpdb->get_row("select CompanyName,Address1,Country from {$prefix}builder_lenders where id={$lender}");
		}
		$lender_data="<table>
		<tr>
		<th>Company</th>
		<th>Contact</th>
		<th>Country</th>
		</tr>";

		foreach ($lenderinfos as $lenderinfo) {
			$lender_data.="<tr>
			<td>".$lenderinfo->CompanyName."</td>
			<td>".$lenderinfo->Address1."</td>
			<td>".$lenderinfo->Country."</td>
			</tr>";
		} 
	}
	$lender_data=isset($lender_data) ? $lender_data : '';
	$shortcodes=array('{{MarketingName}}','{{prequal}}','{{prequal_id}}','{{selected_lenders}}','{{JobUnitNum}}','{{customer_id}}');
	$values=array(
				isset($reservation_data->MarketingName)?$reservation_data->MarketingName:'',
				(!empty($reservation_data->prequal))?$reservation_data->prequal:'#',
				(!empty($reservation_data->prequal_id))?$reservation_data->prequal_id:'#',
				$lender_data,
				isset($reservation_data->JobUnitNum)?$reservation_data->JobUnitNum:'',
				$customer_id
				); 	

		$form_id='elead_email';
			
	$all_templates_byid = get_email_template($form_id);
	$doc_data=stripslashes($all_templates_byid->email_body);
	$availablefields=get_BuyNow_fields();
	if(!empty($availablefields)){
		foreach($availablefields as $key=>$field){
			$fieldkey=$field->field_name;
			$shortcodes[]='{{'.$fieldkey.'}}'; 
			$values[]=isset($reservation_data->$fieldkey) ? $reservation_data->$fieldkey : '';
		}
	}
	$doc_data=str_replace($shortcodes,$values,$doc_data);
	 $mailhtml=stripslashes($doc_data);	

	$subject=$all_templates_byid->subject;
	$to_email=$all_templates_byid->to_email;
	$from_name=$all_templates_byid->from_name;
	
	if($subdivisiondata){
		$subdivname=isset($subdivisiondata->MarketingName) ? $subdivisiondata->MarketingName: '';
		$prequal=isset($reservation_data->prequal) ? site_url().$reservation_data->prequal : '';
		$prequalid=isset($reservation_data->prequal_id) ? site_url().$reservation_data->prequal_id : '';
		$emailaddress=$to_email;
		
		$attachments=array($prequal,$prequalid);
		$admin_email = get_option('admin_email');
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		 
		// Create email headers
		$headers .= 'From: '.$from_name.' '.$admin_email ."\r\n".
			'Reply-To: '.$admin_email ."\r\n" .
			//'Cc: '.$Cc_email ."\r\n" .
			'X-Mailer: PHP/' . phpversion();
		 
		// Compose a simple HTML email message
		// Compose a simple HTML email message
		$ismailsent=wp_mail($emailaddress,$subject,$mailhtml,$headers);
	}	

}

/*
 * 
 * name: BuilderuX_Steps
 * @param
 * @return string
 * 
 */
function BuilderuX_Steps($client=null){
	$html='';
	if(isset($_GET['client']))
		$client=$_GET['client'];
	$sql="contract_type='both'";
	
	$sql="(contract_type='both'";
	
	if($client){
		if($client=='pre')
			$sql.=" OR contract_type='pre'";
		elseif($client=='post')
			$sql.=" OR contract_type='post'";	
	}else{
		$sql.=" OR contract_type='pre'";
	}
	$sql.=")";
	$steps=bx_fetchall('builder_process',array('status=1',$sql),null,null,'display_order ASC');
	$currentstep=isset($_GET['step']) ? $_GET['step'] : $steps[0]->process_id;
	// if login required 
	$activestep=Builderux_get_step($currentstep);
	if(is_user_logged_in()){
		$clients_list = get_the_users('client');
		$html.=get_bx_template('builderux_login_information',array('clients_list'=>$clients_list));
	}
	if($activestep){
		if($activestep->login_required || $activestep->process_slug=='options'){
		// if user is not logged in
			if(!is_user_logged_in()){
				$closebutton=$activestep->process_slug=='options' ? false :true;  // login is requred to save the options 
				$html.=get_bx_template('builderux_login_screen',array('closebutton'=>$closebutton));
			}
		}
	} 
	// login required
	$html.=get_bx_template('builderux_steps',array('activestep'=>$activestep,'steps'=>$steps,'currentstep'=>$currentstep));
	return $html;
}
/*
 * 
 * name: BuilderUx_home_Content
 * @param
 * @return string
 * 
 */
function BuilderUx_home_Content(){
	
	if(!isset($_GET['step']))
		$stepid=Builderux_getdefault_step();
	else	
		$stepid=$_GET['step'];
	// switch case to display content on basis of stepid
	$stepinformation=Builderux_get_step($stepid);
	if(!$stepinformation)
		return builderUX_flash('danger',"This is not a valid step");
	$srtepslug=	$stepinformation->process_slug;
	// update the cookie
	setcookie("bx_current_step", $stepid);
	
	switch ($srtepslug){
		case 'subdivision':
			$content=BuilderUX_Choose_Subdivision($stepid);
		break;
		
		case 'plan':
			$content=BuilderUX_Choose_Plan($stepid);
		break;
		
		case 'unit':
			$content=BuilderUX_Choose_Unit();
		break;
		
		case 'elevation':
			$content=BuilderUX_Choose_Elevation();
		break;
			
		case 'ehome':
			$content=BuilderUX_Choose_Ehome();
		break;
		
		case 'options':
			$content=BuilderUX_Choose_Options();
		break;
		
		case 'send':
			$content=BuilderUX_Choose_SendToSS();
		break;
		
		default :
			$content=BuilderUX_Choose_Subdivision($stepid);
		break;
	}
	
	return $content;
}
/*
 * 
 * name: BuilderUX_Choose_Subdivision
 * @param
 * @return
 * 
 */
function BuilderUX_Choose_Subdivision($stepid){
	$content=''; $limit=BUILDERUX_PAGE_LIMIT;
	$paged=isset($_REQUEST['bx_page']) ? $_REQUEST['bx_page'] : 1; 
	$offset=($paged-1)*$limit;
	$subdivisions=bx_fetchall('builder_subdivision',array(),$limit,$offset);
	
	// get the template
	if(!empty($subdivisions))
		$content.=get_bx_template('builderux_steps_subdivision',array('stepid'=>$stepid,'subdivisions'=>$subdivisions));
	else
		$content.="<div class='bx_no_data'>There is no record to display</div>";
	return $content;
}
/*
 * 
 * name: BuilderUX_Choose_Plan
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_Plan($stepid){
	$content='';
	if(!isset($_REQUEST['subdiv'])){
		wp_redirect(add_query_arg('err','invailid',get_permalink()));
	}	
	$subdiv=$_REQUEST['subdiv'];
	global $wpdb;
	$prefix=$wpdb->prefix;
	
	$subdivision=get_bxdata_byID('builder_subdivision','ID',$subdiv);
	if($subdivision){
		$ID=$subdivision->ID;
		$limit=BUILDERUX_PAGE_LIMIT;
		if(isset($_POST['bx_plan_search'])){

		$wheresql=bx_PlanSearchQuery($_POST);

		}
		$paged=isset($_REQUEST['bx_page']) ? $_REQUEST['bx_page'] : 1; 
		$offset=($paged-1)*$limit;	
		 $sql="Select Distinct {$prefix}builder_masterplan.*, plan.ID as plan_id, 
		(select FullURL from {$prefix}builder_masterplanattachment where MasterPlanID={$prefix}builder_masterplan.ID and `Extension` <> 'pdf' and AttachmentGroup='Website Elevation Image' ORDER BY created DESC LIMIT 0,1) as FullURL 
		from {$prefix}builder_phaseplan plan 
		Inner Join {$prefix}builder_phase on {$prefix}builder_phase.ID = plan.PhaseID 
		Inner Join {$prefix}builder_subdivision on {$prefix}builder_subdivision.ID = {$prefix}builder_phase.SubdivisionID 
		Inner Join {$prefix}builder_masterplan on {$prefix}builder_masterplan.ID = plan.MasterPlanID"; 
		if(isset($_GET['_lotid'])){
			$sql.=" Inner Join {$prefix}builder_lotplanmatrix lm on plan.ID=lm.PhasePlanID";
		}
		$sql.=" Where plan.SubdivisionID = '{$ID}' and plan.isActive=1";
		if(!empty($wheresql)){
		$sql.=$wheresql;	
		}
		if(isset($_GET['_lotid'])){
			$sql.=" and lm.PlanAllowed =1";
		}
		$sql.=" GROUP BY {$prefix}builder_masterplan.ID";
		// get count 
		$total = count($wpdb->get_results($sql));
		
		$sql.=" LIMIT {$offset},{$limit}";
		
		$plans=$wpdb->get_results($sql);
		// get the template
		if(!empty($plans))
			$content.=get_bx_template('builderux_steps_plan',array('total'=>$total,'stepid'=>$stepid,'plans'=>$plans));
		else
			$content.="<div class='bx_no_data'>There is no record to display</div>";
	}else{
		$content.=builderUX_flash('danger',"this subdivision doesn't exists");
	}
	return $content;
	//$sql="SELECT a.id as subid, a.code, b.planname, b.id as planid, b.subdivision_code, b.description, c.subdivisionplanid, c.filename from builder_subdivision a left join builder_subdivisionplan b on b.subdivision_code = a.`code` left join builder_subdivisionattachment c on c.subdivisionplanid = b.id where a.id = {$subdiv} AND b.plan_status = 'Active' group by b.id";	
}

/*
 * 
 * name: BuilderUX_Choose_Options
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_Options(){
	// check if active step
	$step=isset($_GET['step']) ? $_GET['step'] : ''; 
	$stepinfo=Builderux_get_step($step);
	if($stepinfo && $stepinfo->status!=1)
		return builderUX_flash('danger',"You can't edit the options. Please start from first step");
	$content=''; global $wpdb; $products=array();
	$prefix=$wpdb->prefix;
	if(!isset($_REQUEST['subdiv']) || !isset($_REQUEST['plan']))
		wp_redirect(add_query_arg('err','invailid',get_permalink()));
	$subdivision=$_REQUEST['subdiv'];
	$plan=$_REQUEST['plan'];
	$lot_id=$_REQUEST['_lotid'];
	$phaseID=isset($_REQUEST['phaseID']) ? $_REQUEST['phaseID'] : 0;
	
	$subdivisiondata = get_bxdata_byID('builder_subdivision','ID',$subdivision);
	$division = $subdivisiondata->DivisionID;
	// apply the filter
	if(isset($_GET['client']) && $_GET['client']=='post')
		$scope='postcontract';
	elseif(current_user_can('coordinator'))
		$scope='coordinator';
	else
		$scope='precontract';	
	$categories=bx_fetchall('builder_categories',array("parent IS NULL AND DivisionID='{$division}' and (FIND_IN_SET('{$scope}',cat_scope) or cat_scope='' or cat_scope is null)"),null,null);
	$firstcategory=isset($categories{0}->ID) ? $categories{0}->ID : null;
	
	$saved=array();
	// remove the saved options
	if(isset($_REQUEST['review_id'])){
		$reviewid=$_REQUEST['review_id'];
		$reviewdata=get_bxdata_byID('builder_choosehome_request','id',$reviewid);
		if($reviewdata){
			$saved=(array)json_decode($reviewdata->options);
			if($reviewdata->is_granted){
				wp_redirect(add_query_arg('err','finalized_review',get_permalink()));
			}
		}else{
			wp_redirect(add_query_arg('err','invalid_review',get_permalink()));
		}
	}
	if(isset($_POST['flex_options'])){
		$optioncodes=$_POST['flex_options'];
		foreach($optioncodes as $key=>$optioncode){
			$option=$wpdb->get_row("SELECT * from {$prefix}builder_phaseplanoption where OptionCode='{$optioncode}' and SubdivisioNID='{$subdivision}'");
			if($option){
				//if(array_key_exists($option->ID,$saved))
				$saved[$option->ID]=(object)array('status'=>true,'count'=>1,'price'=>$option->UnitPrice,'attributes'=>'','groupid'=>$option->OptionGroupID,'subgroupid'=>$option->Sub_OptionGroupID,'desc'=>$option->OptionLongDesc,'id'=>$option->ID);
			}
		}
	}
	
	// this is temp keys are duplicating
	$newsaved=array();
	foreach($saved as $key=>$value){
		$stroptid=(string)$key;
		$newsaved[$stroptid]=$value;
	}
	// remove
	$subdivision=get_bxdata_byID('builder_subdivision','ID',$subdivision);
	$plan=get_bxdata_byID('builder_phaseplan','ID',$plan);
	$phaselot=get_bxdata_byID('builder_phaselot','ID',$lot_id);
	$phase=get_bxdata_byID('builder_phase','ID',$phaseID);
	if($firstcategory)
		$products=get_builderux_options($firstcategory,null,$subdivision->ID,$plan->ID);	
	$content.=get_bx_template('builderux_steps_options',array('saved'=>$newsaved,'phase'=>$phase,'phaselot'=>$phaselot,'plan'=>$plan,'firstcategory'=>$firstcategory,'subdivision'=>$subdivision,'categories'=>$categories,'products'=>$products));
	return  $content;
}
/*
 * 
 * name: BuilderUX_Choose_Elevation
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_Elevation(){
	
}
 
/*
 * 
 * name: BuilderUX_Choose_Unit
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_Unit(){
	$html='';
	if(isset($_REQUEST['subdiv']))
	{
		$option=get_option('builderux_BuyNow_setting');
		$subdivisionid=$_REQUEST['subdiv'];
		$plan=isset($_REQUEST['plan']) ? $_REQUEST['plan'] : '';
		global $wpdb; $prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
		$sql="select * from {$prefix}builder_subdivision where 	ID LIKE '".$subdivisionid."'";
		$subdivison=$wpdb->get_row($sql);
		if((isset($subdivison->isGoogleMap) && $subdivison->isGoogleMap==1)){
			$gid=isset($subdivison->google_map_id) ? $subdivison->google_map_id : '';
			$args = array('step'=>get_next_step($_GET['step']));
			$html.="<script>
				function chooseNext(step,lotid,unitnum)
				{
					var nextpage='".remove_query_arg('bx_page',add_query_arg($args ))."';
					nextpage+='&_lotid='+lotid;
					window.location.href = nextpage;
				}
			</script>";
			$html.=do_shortcode("[bux_google_maps type='' id='{$gid}']"); 
		}else{
			$action=isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
			$paged=isset($_REQUEST['bx_page']) ? $_REQUEST['bx_page'] : 1; 
			$offset=($paged-1)*$limit;
			if(!empty($_REQUEST['phaseID'])){
				$phaseID = $_REQUEST['phaseID'];
				$sql_query_cond = " AND topo.PhaseID=".$phaseID;
			}else{
				$phaseID = 0;
				$sql_query_cond = " ";
			}
			$option=get_option('builderux_home_setting');
			$filteroption=isset($option['lotstatus']) ? $option['lotstatus'] : '';
			$sql2='';
			if(!empty($filteroption)){
				$filteroption=implode(',',$filteroption);
				$sql2=" and FIND_IN_SET(lot.StatusName,'$filteroption')";
			}
			$table1=$wpdb->prefix.'builder_topoxycoord';
			$table2=$wpdb->prefix.'builder_phaselot'; 
			$table3=$wpdb->prefix.'builder_lotattachment';
			 $query = "SELECT topo.*, lot.*, lot.ID as lotid FROM {$table1} topo 
						Join {$table2} lot on topo.LotID = lot.ID 
						WHERE topo.SubdivisionID={$subdivisionid} {$sql_query_cond} {$sql2}";
			//  $query = "SELECT topo.*, lot.*, lot.ID as lotid FROM {$table2} lot 
			// lEFT Join {$table1} topo on topo.LotID = lot.ID 
			// WHERE lot.SubdivisioID='{$subdivisionid}' and lot.PhasePlanID='{$plan}' and lot.isActive=1 {$sql_query_cond} {$sql2}";
			$query.=" Group BY lot.ID";
			$total=count($wpdb->get_results($query)); 
			$query.=" LIMIT {$offset},{$limit}";	
			//echo $query;	

			$plans = $wpdb->get_results($query);
			$topoimagepath=$wpdb->get_var("SELECT FullURL FROM {$prefix}builder_topoimages topo WHERE SubdivisionID=".$_REQUEST['subdiv'].$sql_query_cond);
			$html.=get_bx_template('builderux_list_unit',array('total'=>$total,'plans'=>$plans,'topoimagepath'=>$topoimagepath,'subdivID'=>$_REQUEST['subdiv'],'phaseId'=>$phaseID,'planId'=>$plan,'step'=>$_REQUEST['step']));
		}
	}else{
		wp_redirect(add_query_arg('err','invailid',get_permalink()));
		//$html.=builderUX_flash('danger',"Invalid Subdivision.");
	}
	return $html;
}
 /*
 * 
 * name: get_plans_by_id
 * @param
 * @return array
 * 
 */
function get_plans_by_id($id){
 global $wpdb;
 $query = "SELECT * FROM ".$wpdb->prefix."builder_phase WHERE isActive=1 and SubdivisionID=".$id;
 $phase = $wpdb->get_results($query);
 return $phase;
}
/*
 * 
 * name: BuilderUX_Choose_Ehome
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_Ehome(){
	if(!isset($_REQUEST['subdiv']) || !isset($_REQUEST['plan'])){
		if(!isset($_GET['CommunicationID']))
			wp_redirect(add_query_arg('err','invailid',get_permalink()));
	}
	@session_start();
	$html='';
	$sid = substr(session_id(),0,5);
	$iframe_url = get_the_iframe($_REQUEST['step']);
	if(empty($iframe_url)){
		$mp=isset($_GET['mp']) ? $_GET['mp'] : ''; 
		$nextpageurl=urlencode(remove_query_arg('bx_page',add_query_arg(array('step'=>get_next_step($_GET['step'])))));
		$iframe_url=site_url()."?redirecturl={$nextpageurl}&BuilderuxFlexplan=true/#/floorplan/{$mp}";
		$html.=get_bx_template('builderux_choose_ehome',array('sid'=>"bx_flexplan",'iframe_url'=>$iframe_url));
		//$html .=Builderux_Flex_data($_REQUEST['plan'],$_REQUEST['step']);
	}else{
		$html .=get_bx_template('builderux_choose_ehome',array('sid'=>$sid,'step'=>$_REQUEST['step'],'iframe_url'=>$iframe_url));
	}
	return $html;
}
/*
 * 
 * name: BuilderUX_Choose_Options
 * @param
 * @return string
 * 
 */
function BuilderUX_Choose_SendToSS(){
	if(isset($_REQUEST['subdiv']) && isset($_REQUEST['plan'])){
		$html='';
		global $wpdb;
		global $current_user;
		wp_get_current_user();
		$userid=(isset($_GET['user']) && $_GET['user']!=0) ? $_GET['user'] : $current_user->ID;
		
		if(!$userid || !is_user_logged_in()){
			return get_bx_template('builderux_login_screen',array('closebutton'=>false));
			die;
		}
		$subdivision=$_GET['subdiv'];
		$plan=$_GET['plan'];
		$lot=isset($_REQUEST['_lotid']) ? $_REQUEST['_lotid'] : '';
		$phase=isset($_POST['phaseID']) ? $_POST['phaseID'] : 0;
		$reviewid=isset($_GET['review_id']) ? $_GET['review_id'] : false;
		$userid=(isset($_GET['user']) && $_GET['user']!=0) ? $_GET['user'] : $current_user->ID;
		$builder=get_bxdata_byID('builder_builder','builder_id',1);
		$subdivinfo=get_bxdata_byID('builder_subdivision','ID',$subdivision);
		
		$data['subdivisionid']=$subdivision;
		$data['planid']=$plan;
		$data['unitid']=$lot;
		$data['phaseID']=$phase; 
		$data['elevationid']=0; 
		$data['options']=json_encode(array()); 
		if($reviewid){
			$data['date_modified']=date('Y-m-d  H:i:s');
			$wpdb->update($wpdb->prefix.'builder_choosehome_request',$data,array('id'=>$reviewid));
		}else{
			$prefix=$wpdb->prefix;
			$maxid=$wpdb->get_var("SELECT COUNT(id) FROM {$prefix}builder_choosehome_request where user_id='{$userid}'");
			$maxid=$maxid+1;
			$data['scenario_name']='Scenario '.$maxid;
			$data['date_added']=date('Y-m-d H:i:s');
			$data['user_id']=$userid;
			$data['user_email']=$current_user->user_email;	
			$data['date_modified']=date('Y-m-d H:i:s');
			$data['is_granted']=0;
			$wpdb->insert($wpdb->prefix.'builder_choosehome_request',$data);
			$createdreview=$wpdb->insert_id;
		}	
		$data['type']='precontract';
		$statdata=(object) $data;
		BX_submit_Stat($statdata);	
		//SubmitReviewToSS($createdreview); // submit lead to SS
		if(isset($createdreview) && $createdreview)
			$redirect=add_query_arg(array('action'=>'submitsslead','reviewid'=>$createdreview));
		else	
			$redirect=add_query_arg(array('action'=>'reviewlist'));
			
		wp_redirect($redirect);	
	}else{
		wp_redirect(add_query_arg('err','invailid',get_permalink()));
		//$html.=builderUX_flash('danger',"Invalid Subdivision.");
	}
}
/*
 * 
 * name: BuilderUx_reviewlist
 * @param
 * @return string
 * 
 */
function BuilderUx_reviewlist($selected=null){
	$html="";
	global $current_user; global $wpdb; $limit=BUILDERUX_PAGE_LIMIT;
	$prefix=$wpdb->prefix;
	if(isset($_GET['user']) && $_GET['user'] !='' && $_GET['user'] != 0){
		$user_id = $_GET['user'];
	}else{
		$current_user = wp_get_current_user();
		if($current_user && !empty($current_user->data)){
			$user_id = get_current_user_id();
		}
	}
	if(!$user_id || !is_user_logged_in())
		$html.=get_bx_template('builderux_login_screen',array('closebutton'=>false));
	else{	
		$useremail=$current_user->user_email;	
		$mysql="SELECT rq.*, lot.LotUnitNum, plan.MarketingName as planname, sub.CustPortalLogoURL, sub.ID as sub_id, sub.Email, sub.MarketingName, sub.Fax, sub.Country, sub.County, sub.MarketingDescription  FROM `{$prefix}builder_choosehome_request` as rq 
				LEFT JOIN `{$prefix}builder_subdivision` sub ON sub.ID=rq.subdivisionid 
				LEFT JOIN `{$prefix}builder_phaselot` lot ON lot.ID=rq.unitid
				LEFT JOIN `{$prefix}builder_phaseplan` plan ON plan.ID=rq.planid
				where rq.is_granted =0 and rq.user_id = {$user_id} ORDER BY date_added DESC LIMIT 0,{$limit}";
		$reviewlists=$wpdb->get_results($mysql);
		// get the template
		$html.=get_bx_template('builderux_review_lists',array('selected'=>$selected,'reviewlists'=>$reviewlists));
	}
	return $html; 
}
/*
 * 
 * name: get_the_iframe
 * @param
 * @return string
 * 
 */
function get_the_iframe($process_id){
 $html="";
 global $wpdb;
 $prefix=$wpdb->prefix;
 $iframe_url = $wpdb->get_var("SELECT ehome_url FROM {$prefix}builder_process WHERE process_slug='ehome' AND process_id={$process_id}");
 return $iframe_url; 
}
function BuilderUx_generatet_pemphlate(){
	$id = $_GET['review'];
	global $current_user; wp_get_current_user();
	$userid=$current_user->ID;
	if(!$userid)
		return builderUX_flash('danger',"you need to login first.");
		
	generate_pamphlet_pdf($id);	
}
function generate_pamphlet_pdf($rowid)
{
	// create new PDF document
	global $wpdb;   
	$prefix=$wpdb->prefix;
	$target_dir = plugins_url('builderux/images/'); 
	$info = $wpdb->get_row("select * from {$prefix}builder_choosehome_request where id ='{$rowid}'");
	//echo "select * from {$prefix}builder_choosehome_request where id ='{$rowid}'";
	global $current_user; wp_get_current_user();
	$userid=$current_user->ID;
	
	if(!$userid){
		wp_redirect(site_url());
		exit;
	}
	if(empty($info))
	{
		wp_redirect(site_url());
		exit;
	}
	$subdivinfo = get_bxdata_byID('builder_subdivision','ID',$info->subdivisionid);
	$subphaseplan = get_bxdata_byID('builder_phaseplan','ID',$info->planid);
	$subphaselot = get_bxdata_byID('builder_phaselot','ID', $info->unitid);
	
	$imgsql = "SELECT 
	a.FullUrl, a.FileName
	FROM {$prefix}builder_phaseplanattachment a 
	LEFT JOIN {$prefix}builder_phaseplan spp ON spp.ID = a.PhasePlanID
	LEFT JOIN {$prefix}builder_phase sp ON sp.ID = spp.PhaseID
	LEFT JOIN {$prefix}builder_subdivision s ON s.ID = sp.SubdivisionID
	LEFT JOIN {$prefix}builder_division sd ON sd.ID = s.DivisionID
	LEFT JOIN {$prefix}builder_builder sm ON sm.ID=sd.BuilderID 
	WHERE a.PhasePlanID ='".$info->planid."'";
	$imginfo = $wpdb->get_results($imgsql);
	# begin content here
	//$content = '<p style="font-size: 13px;">
	//<strong>You can contact us anytime at</strong> <br />
	//<strong>'.$subdivinfo->subleadsemail.'</strong><br />
	//<strong>'.$subdivinfo->fax.'</strong>
	//</p><hr><strong>'.$subdivinfo->name.'</strong> <br />';
	$info_logo=BUILDERUX_PLACEHOLDER;
	if(@getimagesize($subdivinfo->CustPortalLogoURL) && strlen($subdivinfo->CustPortalLogoURL) > 0)
	{
		$info_logo= $subdivinfo->CustPortalLogoURL;
	}
	//$content .= '<p style="font-size: 13px;>'.$subdivinfo->marketingdescription.'</p><hr><p style="font-size: 13px;><strong>'.$subphaseplan->planname.'</strong> ($'.$subphaseplan->baseprice.')</p>';
	$plane_images='';
	if(count($imginfo) > 0)
	{;
		$xcount = 0;
		foreach($imginfo as $key => $val)
		{
			$xcount++;
			$imgsrc =  bx_addhttp($val->FullUrl);
			if(@getimagesize($imgsrc) && strlen($val->FullUrl) > 0)
			{
				$optimgx = str_replace(" ","%20",$imgsrc);
				$file_headers_optimgx = @get_headers($optimgx); 
				$plane_images .= '<img src="'.$optimgx.'" style="margin-right: 10px;width: 100px;">';	
			}
			if($xcount == 3)
			{
				$xcount = 0;
				$plane_images .= '<br />';
			}
		}
	}
	$bimg = '';
	if(isset($elevinfo->image))
	{
		$bimg = basename($elevinfo->image);
	} 
	$imagename2 = $target_dir.$bimg;
	$selected_options='';
	//$content .= '<p style="font-size: 17px; padding-bottom: 10px; border-bottom: solid 2px #000000;"><strong>Option Selected</strong></p><hr>';
	$selected_options .= '<table style="width: 900px;">';
	
	if(strlen($bimg) > 0)
	{
		$selected_options .= '<tr><td>';
		$file_headers_imagename2 = @get_headers($imagename2); 
		if($file_headers_imagename2[0] == 'HTTP/1.1 404 Not Found' || $file_headers_imagename2[0] == 'HTTP/1.1 400 Bad Request') 
		{}
		else
		{
			$selected_options .= '<img src="'.$imagename2.'" style="width: 100px;">';
		}
		$selected_options .= '</td>';
		$selected_options .= '<td style="text-align: center; padding: 10px; width: 235px;"><strong></strong></td><td style="text-align: center; padding: 10px; width: 235px;"> $</td></tr>
	<tr><td colspan="3"><hr></td></tr>'; 
	}
	
	
	$optdata = json_decode($info->options);
	$totalopt = 0;
	if(count($optdata) > 0)
	{
		foreach($optdata as $key => $val)
		{
			if(isset($val->id)){
				$totalopt = $totalopt + $val->price;
				$optimg = get_option_image($val->id);
			
				if(strlen($optimg) > 0)
				{
					$target_file =  bx_addhttp($optimg);
					$file_headers = @get_headers($target_file); 
					$selected_options .= '<tr><td>';
		
					if($file_headers[0] == 'HTTP/1.1 404 Not Found' || $file_headers[0] == 'HTTP/1.1 400 Bad Request') 
					{}
					else
					{
						$selected_options .= '<img src="'.$target_file.'" style="width: 100px;">';
					}
					$selected_options .= '</td>';
				}
				else
				{
					$selected_options .= '<tr><td><strong>No Image Available</strong></td>';
				}
				$selected_options .= '<td style="text-align: center; padding: 10px;"><strong>'.$val->desc.'</strong></td>
		<td style="text-align: center; padding: 10px;"> $'.$val->price.'</td></tr>';
			}
		}
	}
	
	$selected_options .= '<tr><td colspan="3"><hr></td></tr></table>';
	
	//selected options
	if(@getimagesize($info->flex_img)){
		$flexplanoptions="<strong>Flex Floor Plan</strong><br />";
		$flexplanoptions.="<img style='width:700px' src='{$info->flex_img}'/>";
	}
	$elevinfo_price = 0;
	if(isset($elevinfo->price))
	{
		$elevinfo_price = $elevinfo->price;
	} 
	$plane_price='';
	$general_total=$elevinfo_price + $totalopt;
	if(isset($subphaselot->Premium)){
		$general_total=$general_total+$subphaselot->Premium;
	}
	if(isset($subphaseplan->PhasePlanPrice)){
		$general_total=$general_total+$subphaseplan->PhasePlanPrice;
	}
	$content=stripslashes(get_option('builderux_pdf_template'));
	$shortcodes=array('{{info_email}}','{{info_fax}}','{{info_name}}','{{info_logo}}','{{info_description}}','{{plan_name}}','{{plan_baseprice}}','{{plan_price}}','{{plan_images}}','{{selected_options}}','{{lot_premium}}','{{options_total}}','{{general_total}}');
	$values=array(
				$subdivinfo->Email,
				$subdivinfo->Fax,
				$subdivinfo->MarketingName,
				$info_logo,
				isset($subdivinfo->MarketingDescription) ? $subdivinfo->MarketingDescription : '-',
				isset($subphaseplan->MarketingName) ? $subphaseplan->MarketingName : '-',
				isset($subphaseplan->PhasePlanPrice) ? $subphaseplan->PhasePlanPrice : '00',
				isset($subphaseplan->PhasePlanCost) ? $subphaseplan->PhasePlanCost : '00',
				$plane_images,
				$selected_options,
				isset($subphaselot->Premium) ? $subphaselot->Premium : '00',
				$totalopt,
				$general_total);
	$content= str_replace($shortcodes,$values,$content);
	if(isset($flexplanoptions))
		$content.=$flexplanoptions;
	#end content
	try
	{
		ob_start();	
		header("Content-type:application/pdf");	
		$html2pdf = new HTML2PDF('P', 'A4', 'en',true,'UTF-8',array(5, 30, 25, 8));
		$html2pdf->writeHTML($content);
		ob_end_clean();
		$html2pdf->Output('example.pdf');
		ob_end_flush();
	}
	catch(HTML2PDF_exception $e) 
	{
		echo $e;
		exit;
	}
}
function get_option_image($optid)
{	
	global $wpdb; 
	$prefix=$wpdb->prefix;
	if( empty( $optid ) ) {
		return null;
	}  
	$info = $wpdb->get_row("SELECT 	ImageURL FROM {$prefix}builder_phaseplanoption WHERE ID = '{$optid}'");
	
	return $info->ImageURL;
}
/*
 * 
 * name: BuilderUX_get_Plans
 * @param int $subdiv
 * @return string
 * 
 */
function BuilderUX_get_Plans($subdiv){
	global $wpdb;
	$prefix=$wpdb->prefix;
	$content='';
	$subdivision=get_bxdata_byID('builder_subdivision','ID',$subdiv);
	if($subdivision){
		$ID=$subdivision->ID;
		$limit=BUILDERUX_PAGE_LIMIT;	
		$sql="Select Distinct {$prefix}builder_masterplan.*, max(plan.ID) as plan_id, att.FullURL, att.`AttachmentGroup` 
			from {$prefix}builder_masterplan
			Inner Join {$prefix}builder_phaseplan plan  on {$prefix}builder_masterplan.ID = plan.MasterPlanID 
			Inner join {$prefix}builder_masterplanattachment att on att.MasterPlanID={$prefix}builder_masterplan.ID 
			Where plan.SubdivisionID = '{$ID}' GROUP BY {$prefix}builder_masterplan.ID";
		// get count 
		$total = count($wpdb->get_results($sql));
		
		$sql.=" LIMIT 0,{$limit}";
		$plans=$wpdb->get_results($sql);
		// get the template
		$content.=get_bx_template('builderux_plans',array('total'=>$total,'plans'=>$plans));
	}else{
		$content.=builderUX_flash('danger',"This subdivision doesn't exists");
	}
	return $content;
}
/*
 * 
 * name: BuilderUX_get_Plan
 * @param int $planid
 * @return string
 * 
 */
function BuilderUX_get_Plan($planid){
	global $wpdb;
	$prefix=$wpdb->prefix;
	$content='';
	$sql="Select Distinct master.*, max(plan.ID) as plan_id, att.FullURL, att.`AttachmentGroup` from {$prefix}builder_phaseplan plan 
		Inner Join {$prefix}builder_phase on {$prefix}builder_phase.ID = plan.PhaseID 
		Inner Join {$prefix}builder_subdivision on {$prefix}builder_subdivision.ID = {$prefix}builder_phase.SubdivisionID 
		Inner Join {$prefix}builder_masterplan master on master.ID = plan.MasterPlanID 
		Inner join {$prefix}builder_masterplanattachment att on att.MasterPlanID=master.ID 
		Where master.ID = '{$planid}' GROUP BY master.ID";
	$plan=$wpdb->get_row($sql);
	if($plan){
		$planname=$plan->MarketingName;
		$content.=get_bx_template('builderux_plan',array('plan'=>$plan));
	}else{
		$content.=builderUX_flash('danger',"This Plan doesn't exists");
	}
	return $content;
}
/*
 * 
 * name: BuilderUX_get_Subdivision
 * @param mixed $subdiv
 * @return string
 * 
 */
function BuilderUX_get_Subdivision($subdiv){
	global $wpdb;
	$prefix=$wpdb->prefix;
	$content='';
	$subdivision=get_bxdata_byID('builder_subdivision','ID',$subdiv);	
	if($subdivision){
		$mysql="Select MIN(plan.PhasePlanPrice) as price_min, MAX(plan.PhasePlanPrice) as price_max, MIN(SquareFeet) as sqft_min, MAX(SquareFeet) as sqft_max from {$prefix}builder_phaseplan plan 
			Inner Join {$prefix}builder_phase on {$prefix}builder_phase.ID = plan.PhaseID 
			Inner Join {$prefix}builder_subdivision on {$prefix}builder_subdivision.ID = {$prefix}builder_phase.SubdivisionID
			Inner Join {$prefix}builder_masterplan on {$prefix}builder_masterplan.ID = plan.MasterPlanID 
			Inner join {$prefix}builder_masterplanattachment att on att.MasterPlanID={$prefix}builder_masterplan.ID 
			Where plan.SubdivisionID = '{$subdiv}' GROUP BY {$prefix}builder_masterplan.ID LIMIT 1";
		$pricedata=$wpdb->get_row($mysql);
		$content.=get_bx_template('builderux_subdivision',array('pricedata'=>$pricedata,'subdivision'=>$subdivision));
	}else{
		$content.=builderUX_flash('danger',"This Subdivision doesn't exists");
	}
	return $content;
}
/*
 * 
 * name: unknown
 * @param
 * @return
 * 
 */
function Bux_moveinready_data($type='Spec',$subdiv=null){
	global $wpdb;
	$prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
	$paged=isset($_REQUEST['bx_page']) ? $_REQUEST['bx_page'] : 1; 
	$offset=($paged-1)*$limit;
	$sql="Select Distinct mp.*, plot.*, plot.Restrictions,plot.ID as phaselot, att.ID as attachid, mp.ID as masterid, plan.ID as plan_id, att.FullURL, att.`AttachmentGroup` from {$prefix}builder_phaseplan plan 
		LEFT Join {$prefix}builder_phase on {$prefix}builder_phase.ID = plan.PhaseID 
		LEFT Join {$prefix}builder_subdivision on {$prefix}builder_subdivision.ID = {$prefix}builder_phase.SubdivisionID 
		LEFT Join {$prefix}builder_masterplan mp on mp.ID = plan.MasterPlanID 
		LEFT join {$prefix}builder_masterplanattachment att on att.MasterPlanID=mp.ID 
		LEFT join {$prefix}builder_phaselot plot on plot.PhaseID={$prefix}builder_phase.ID
		where plot.StatusName='{$type}'";
    if($subdiv)
		$sql.=" and {$prefix}builder_subdivision.ID={$subdiv}";	
		
	$sql.=" GROUP BY mp.ID";	
		// get count 
	$total = count($wpdb->get_results($sql));
		
	$sql.=" LIMIT {$offset},{$limit}";
	$plans=$wpdb->get_results($sql);
	//echo "<pre>"; print_r($plans);
	if(!empty($plans))
		return get_bx_template('moveinready',array('total'=>$total,'plans'=>$plans));
	else
		return "<div class='bx_no_data'>There is no record to display</div>";
}

function Bux_moveinready_reserve($subdivisionid){
	$page="Buy now";
	global $wpdb;
	$prefix=$wpdb->prefix; 
	$table1=$wpdb->prefix.'builder_topoxycoord';
	$table2=$wpdb->prefix.'builder_phaselot'; 
	$table3=$wpdb->prefix.'builder_lotattachment';
	$table5=$wpdb->prefix.'builder_phaseplan';
	$masterattachmenttbl=$wpdb->prefix.'builder_masterplanattachment';
	$table_phaseplanattachment = $wpdb->prefix.'builder_phaseplanattachment';
	$optionbuynow=get_option('builderux_BuyNow_setting');
	$subsql="";	
	$filteroption=(isset($optionbuynow['lotstatus'])) ? $optionbuynow['lotstatus'] : '';
	if(!empty($filteroption)){
		if(count($filteroption)==1 && $filteroption[0]=='Available'){
			$subsql.="";
		}else{
			$filteroption=implode(',',$filteroption);
			$subsql.=" and FIND_IN_SET(lot.StatusName,'$filteroption')";
		}
	}
	$query = "SELECT lot.*, pp.*, mp.*, lot.ID as lotid FROM {$prefix}builder_phaselot lot 
	LEFT JOIN {$prefix}builder_phaseplan pp ON pp.ID=lot.PhasePlanID
	LEFT JOIN {$prefix}builder_masterplan mp ON pp.MasterPlanID=mp.ID
	WHERE lot.SubdivisioNID='{$subdivisionid}' and NULLIF(ApprovedBy, '') IS NOT NULL and `Hold` <> 1 {$subsql} Group BY lot.ID";
	
	$unit = $wpdb->get_results($query);
	return get_bx_template('moveinready_reserve',array('plans'=>$unit,'detaillink'=>true,"buynow"=>true));
}
/*
 * 
 * name: Bux_moveinready_single
 * @param int $masterid
 * @return string
 * 
 */
function Bux_moveinready_single($masterid,$lot){
	global $wpdb;
	$prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
	 $sql="Select Distinct mp.*, plot.	PlanCost as plancost, sub.SubdivisionNum, sub.MarketingName, plot.*,plot.ID as phaselot, att.ID as attachid, mp.ID as masterid, plan.ID as plan_id, att.FullURL, att.`AttachmentGroup` from {$prefix}builder_phaseplan plan 
		Inner Join {$prefix}builder_phase on {$prefix}builder_phase.ID = plan.PhaseID 
		Inner Join {$prefix}builder_subdivision sub on sub.ID = {$prefix}builder_phase.SubdivisionID 
		Inner Join {$prefix}builder_masterplan mp on mp.ID = plan.MasterPlanID 
		Inner join {$prefix}builder_masterplanattachment att on att.MasterPlanID=mp.ID 
		Inner join {$prefix}builder_phaselot plot on plot.PhaseID={$prefix}builder_phase.ID
		where mp.ID='{$masterid}' GROUP BY mp.ID";
	$plan=$wpdb->get_row($sql);
	$MasterOptionID=$plan->masterid;
	$PhaseID=$plan->PhaseID;
	$DivisionID=$plan->DivisionID;
	//$optionssql="select * from {$prefix}builder_phaseplanoption where PhaseID='{$PhaseID}' and DivisionID='{$DivisionID}' GROUP BY OptionDesc";
	//$option_result=get_lotoptions($lot,$masterid);
	//print_r($option_result); die;
	//$option_result=bx_fetchall('builder_phaseplanoption',array("MasterOptionID='{$MasterOptionID}' and PhaseID='{$PhaseID}' and DivisionID='{$DivisionID}'"));
	$option_result=array();
	if(!empty($plan)){
		$address=array($plan->Address1,$plan->City,$plan->State); 
		$address=implode(', ',$address);
		return get_bx_template('moveinready_single',array('address'=>$address,'plan'=>$plan,'option_result'=>$option_result));	
	}else
		return builderUX_flash('danger',"Invalid Record");	
}
function get_lotoptions($id,$masterplanid)
{
	global $wpdb;
	$prefix=$wpdb->prefix;
	$sql="SELECT opt.OptionLongDesc, opt.OptionDesc, opt.ID FROM {$prefix}builder_phaseplanoption opt
		LEFT JOIN {$prefix}builder_phaselot lot ON lot.PhasePlanID=opt.PhasePlanID
		";
	/*$sql1 = "SELECT spl.SubdivisioNID FROM {$prefix}builder_phaselot spl 
			 LEFT JOIN {$prefix}builder_phase sp ON sp.ID=spl.PhaseID
			 WHERE spl.id = $id";	
	$result = $wpdb->get_row($sql1);
	$subdivision_code = isset($result->SubdivisioNID) ? $result->SubdivisioNID : '';
	$sql2 = "SELECT * FROM {$prefix}builder_phaseplanoption
			WHERE PhasePlanID IN ( SELECT ID FROM {$prefix}builder_masterplan sp WHERE subdivision_code = '$subdivision_code' AND masterplanid = $masterplanid) 
			 AND optioncode IN (SELECT OptionCode FROM {$prefix}builder_phaseplanoption WHERE lot_id = $id) AND option_status='Active' ORDER BY optiongroupname";
			 
	*/
	$details = $wpdb->get_results($sql);															
																													
	return $details;																								
}
/*name: BuilderUX_get_availablelots
*@param int $phaselot_id
*@ return string
 */
function BuilderUX_get_availablelots($phaselot_id){
	global $wpdb;
	$prefix=$wpdb->prefix;
	$content='';
	$sql="Select Distinct lot.*, plan.ID as plan_id,masterop.ID as master_id,master.bedrooms,master.bathrooms,subdivv.MarketingName,att.FullURL from {$prefix}builder_phaseplan plan 
	Inner Join {$prefix}builder_phaselot lot on lot.phaselot_id = $phaselot_id 
	Inner join {$prefix}builder_phaseplanattachment att on att.PhasePlanID=lot.PhasePlanID 
	Inner join {$prefix}builder_subdivision subdivv on subdivv.ID=lot.SubdivisioNID 
	Inner Join {$prefix}builder_masterplan master on master.ID = plan.MasterPlanID
	Inner Join {$prefix}builder_masteroptions masterop on masterop.DivisionID = lot.DivisionID
	Where lot.phaselot_id = '{$phaselot_id}' GROUP BY lot.phaselot_id";
	$planss=$wpdb->get_row($sql);
	$MasterOptionID=$planss->master_id;
	$PhaseID=$planss->PhaseID;
	$DivisionID=$planss->DivisionID;
	$option_result=bx_fetchall('builder_phaseplanoption',array("MasterOptionID='{$MasterOptionID}' and PhaseID='{$PhaseID}' and DivisionID='{$DivisionID}'"));
	if(!empty($planss)){
		$content.=get_bx_template('builderux_availablelot',array('planss'=>$planss,'option_result'=>$option_result));
	}else{
		$content.=builderUX_flash('danger',"This Lots doesn't exists");
	}
	return $content;
}
function builderux_copy_scenario($scenario_id,$userid) {	
	global $wpdb;																															
	$prefix=$wpdb->prefix;
	// get user data
	$user_info = get_userdata($userid);
	$email = $user_info->user_email;
	$user_id=$user_info->ID;
	// get scenario
	$scenario = $wpdb->get_row("select * from {$prefix}builder_choosehome_request where id='{$scenario_id}'");
	$new_name=builderux_scenario_slug($scenario->scenario_name);
	
	// prepare data
	$data = array(
		'user_id' => $user_id,
	 	'user_email' => $email,
	 	'scenario_name' => $new_name,
	 	'subdivisionid' => $scenario->subdivisionid,
	 	'planid' => $scenario->planid,
	 	'unitid' => $scenario->unitid,
	 	'elevationid' => $scenario->elevationid,
	 	'options' => $scenario->options,
	 	'is_granted' => $scenario->is_granted,
	 	'date_added' => date('Y-m-d H:i:s')
	);
	$insert = $wpdb->insert( $prefix.'builder_choosehome_request', $data );
	if( is_wp_error( $insert ) ) {
		$error_message = $insert->get_error_message();
	}
	$mysql="SELECT rq.*, sub.CustPortalLogoURL, sub.ID as sub_id, sub.Email, sub.MarketingName, sub.Fax, sub.Country, sub.County, sub.MarketingDescription  FROM `{$prefix}builder_choosehome_request` as rq 
			INNER JOIN `{$prefix}builder_subdivision` sub ON sub.ID=rq.subdivisionid where rq.user_id = {$user_id}";
	$reviewlists=$wpdb->get_results($mysql);
	if( $insert == 1 ) {
		return $id = $wpdb->insert_id;
	}else{
		return false;
	}
}
function builderux_scenario_slug($name){
	$newname=$name.' copy';
	return check_scenario_slug($newname,0);
	
} 
/*
 * 
 * name: check_scenario_slug
 * @param string , int
 * @return string
 * 
*/
function check_scenario_slug($name,$index){
	global $wpdb;
	if($index==0)
		$newname=$name;
	else
		$newname=$name.$index;
	$scenarios = $wpdb->get_results('select * from '.$wpdb->prefix.'builder_choosehome_request where scenario_name LIKE "'.$newname.'"');
	if(count($scenarios)){
		$index++;
		return check_scenario_slug($name,$index);
	}
	else{
		return $newname;
	}
}
function BuilderUx_handel_PostContract(){
	$reviewid=$_GET['review_id'];
	$review=get_bxdata_byID('builder_choosehome_request','ID',$reviewid);
	if($review){
		$optionsteps=get_bxdata_byID('builder_process','process_slug','options');
		$redirecturl=remove_query_arg('usertype',add_query_arg(array('subdiv'=>$review->subdivisionid,'plan'=>$review->planid,'phaseID'=>$review->phaseID,'_lotid'=>$review->unitid,'step'=>$optionsteps->process_id,'client'=>'post')));
		wp_redirect($redirecturl);
	}else{
		return builderUX_flash('danger',"This is an invalid request . Please check and try again");
	}	
}
function BuilderUx_finalize_review($request){
	if(isset($request['review_id'])&& !empty($request['review_id'])){
		$reviewid=$request['review_id'];
		$review=get_bxdata_byID('builder_choosehome_request','ID',$reviewid);
		if(!$review)
			return builderUX_flash('danger',"This record is invalid . Please try again.");
		
		$options=get_option('builderux_BuyNow_setting');
		//$wsdl="http://dev1.digitaltransfusion.net/Elead.asmx?op=SubmitEleadXML&wsdl";
		$wsdl=isset($options['topo_ss_wsdl']) ? $options['topo_ss_wsdl'] : '';
		$sGUID=isset($options['topo_ss_guid']) ? $options['topo_ss_guid'] : '';
		$buildername=isset($options['topo_ss_builder']) ? $options['topo_ss_builder'] : '';
		
		$userinfo=get_userdata($review->user_id);	
		// user details
		$fname=isset($userinfo->first_name) ? $userinfo->first_name : '';
		$lname=isset($userinfo->last_name) ? $userinfo->last_name : '';
		$email=isset($userinfo->user_email) ? $userinfo->user_email : '';
		
		global $wpdb;
		$prefix=$wpdb->prefix;
		
		$sql="SELECT mp.PlanModelNum FROM `{$prefix}builder_choosehome_request` h LEFT JOIN {$prefix}builder_phaseplan p ON h.`planid`=p.ID LEFT JOIN {$prefix}builder_masterplan mp ON mp.ID=p.MasterPlanID where h.id=1";
		
		$data=$wpdb->get_row($sql);
		// lot details
		$lotdetail=get_bxdata_byID('builder_phaselot','ID',$review->unitid);
		$JobUnitNum=isset($lotdetail->JobUnitNum) ? $lotdetail->JobUnitNum: '';
		$subdivisionid=$review->subdivisionid;
		$PlanModelNum=isset($data->PlanModelNum) ? $data->PlanModelNum : '';
		// phase detail
		$phasedetail=get_bxdata_byID('builder_phase','ID',$review->phaseID);
		$phasename=isset($phasedetail->PhaseName) ? $phasedetail->PhaseName: '';
		$selectedoptions=json_decode($review->options);
		$scenario_name=isset($review->scenario_name) ? $review->scenario_name : '';
		$date=date('m/d/Y h:i:s A');
		$xml='';
		$xml.='<?xml version="1.0" encoding="utf-8"?>
			<RootNode>
			 <Lead>
			  <Contact FirstName="'.$fname.'" LastName="'.$lname.'" Email="'.$email.'" Phone="" StreetAddress="" StreetAddress2="" City="" State="" Country="" PostalCode="" VisitDate="'.$date.'" ContactType="" SendResponse="" IsWebLead="" IPAddress="" WorkPhone="" ScenarioName="'.$scenario_name.'" WorkPhoneExt="" MobilePhone="" Fax="" Pager="" Title="" UserName="support" Rank="" TwitterID="" TwitterName="" AllowBeBacks="" ProcessAllScenarios="" UpdateAllDemographics="" DontUpdateBeBackDemos="" ValidateByEmail="" SitesDefaultAddress="" CoFirstName="" CoTitle="" CoLastName="" CoEmail="" CoPhone="" CoWorkPhone="" CoFax="" CoPager="" CoMobilePhone="" CoStreetAddress="" CoStreetAddress2="" CoCity="" CoState="" CoPostalCode="" CoCountry="" />
			  <Qualifications Comments="" Note="" />
			  <PropertyInterest BuilderName="'.$buildername.'" MasterCommunity="" CommunityNumber="" CommunityName="abc1231231" PlanNumber="" SpecNumber="" SpecAddress="" />
			  <Selections>
			   <Lot>
				<GoToContract>True</GoToContract>
				<JobUnitNum>'.$JobUnitNum.'</JobUnitNum>
				<Phase>'.$phasename.'</Phase>
				<PlanModelNum>'.$PlanModelNum.'</PlanModelNum>
				<SubdivisionID>'.$subdivisionid.'</SubdivisionID>
			   </Lot>
			   <Options>';
			   foreach($selectedoptions as $key=>$option){
				   $optid=isset($option->id) ? $option->id : 0;
				   $optiondata=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."builder_phaseplanoption where ID={$optid}");
				   $OptionCode=isset($optiondata->OptionCode) ? $optiondata->OptionCode : '';
				   $OptionLongDesc=isset($optiondata->OptionLongDesc) ? $optiondata->OptionLongDesc : $option->desc;
				   $attributes=json_decode(stripslashes($option->attributes)); 
				   $xml.='<Option>
					 <IsCustom>False</IsCustom>
					 <Code>'.$OptionCode.'</Code>
					 <ShortDesc>'.$option->desc.'</ShortDesc>
					 <LongDesc>'.$OptionLongDesc.'</LongDesc>
					 <Qty>'.$option->count.'</Qty>
					 <Price>'.$option->price.'</Price>';
					 $xml.="<Attributes>";
						if(!empty($attributes)){
							foreach($attributes as $key=>$attr){
								$attrbutedata=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."builder_attributes where attribute_id={$attr->attribute}");
								$ItemID=isset($attrbutedata->ItemID) ? $attrbutedata->ItemID : '';
								$GroupID=isset($attrbutedata->GroupID) ? $attrbutedata->GroupID : '';
								$ItemGroupID=isset($attrbutedata->ItemGroupID) ? $attrbutedata->ItemGroupID : '';
								$Question=isset($attrbutedata->Question) ? $attrbutedata->Question : '';
								$answer=isset($attr->answer) ? $attr->answer : '';
								$xml.="<Attribute TextData='' Price='{$option->price}' GroupName='{$Question}' AttributeName='{$answer}' GroupID='{$GroupID}' ItemID='{$ItemID}' ItemGroupID='{$ItemGroupID}'></Attribute>"; 
							}
						}
						$xml.="</Attributes>";	
					$xml.='</Option>';
				}
				$xml.='</Options>
			   <ContractDocumentURL></ContractDocumentURL>
			   <ContractDocumentDescription>BuilderUX Choose Home '.$date.'</ContractDocumentDescription>
			  </Selections>
			 </Lead>
			</RootNode>
			';	
			
		try{
			 $options = array(
                'soap_version'=>SOAP_1_2,
                'exceptions'=>true,
                'trace'=>1,
                'cache_wsdl'=>WSDL_CACHE_NONE
            );
			$client = new SoapClient($wsdl);
			$result = $client->SubmitEleadXML(array(
				'sGUID' => $sGUID,
				'xml' => $xml
			));
			//lock the secenerio
			$wpdb->update($prefix.'builder_choosehome_request',array('is_granted'=>1),array('id'=>$reviewid));
			return builderUX_flash('success',"you have submitted your request successfully");
		}catch(Exception $e){
			
			return builderUX_flash('danger',"Some error to submit your request.".$e->getMessage());
		}
	}else{
		return builderUX_flash('danger',"Your request is invalid . Please try again.");
	}
}
function BuilderUX_Iframe_Postcontract($clientid,$Token){
	$html='';
	$user=get_users(array('meta_key' => 'ID', 'meta_value' => $clientid));
	if($user){
		if(!function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-load.php"); 
			include(ABSPATH . "wp-includes/pluggable.php"); 
		}
		$html.=Bx_Iframe_Scripts();
		$html.=builderux_inline_script(true);
		//wp_head();
		$email=$user[0]->data->user_email;
		$id=$user[0]->ID;
		bx_login_userByEmail($email,$id);
		$html.='<div id="bx_choose_home" class="bx_choosehome_iframe bx_choosehome_wrap">';
			$html.=BuilderuX_Steps('post');
			$html.='<div id="bx_choose_home_content" class="bx_choose_home_content row">';
					$html .= '<section class="lists">';
						$html.='<div class="">';
							if(isset($_GET['action']) && $_GET['action'] == 'reviewlist'){
								$html.=BuilderUx_reviewlist();
							}else
								$html.=BuilderUx_home_Content();
						$html .= '</div>';
					$html.='</section>';
			// $html .= bux_pagination(bx_totalrecords('builder_subdivision'));
			$html .= '</div>';
		$html .= '</div>';	
								
	}else{
		$html.=builderUX_flash('danger',"Invalid User");
	}
	builderux_inline_modal();
	return $html;
	die;
}
function Bx_Iframe_Scripts(){
	$scripts='';
	
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/assets/css/builderux_custom.min.css"></link>';
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/common/assets/css/select2.min.css"></link>';
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/assets/css/builderux.css"></link>';
	
	$scripts.='<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"></link>';
	$scripts.='<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>';
	$scripts.='<script type="text/javascript" src="https://js.stripe.com/v2/"></script>';
	$scripts.='<script type="text/javascript" src="'.BUILDERUX_DIR_URL .'/common/assets/js/bootstrapValidator-min.js"></script>';
	$scripts.='<script type="text/javascript" src="'.BUILDERUX_DIR_URL .'/common/assets/js/select2.full.js"></script>';
	$scripts.='<script type="text/javascript" src="'.BUILDERUX_DIR_URL .'/common/assets/js/bootstrap.min.js"></script>';
	$scripts.='<script type="text/javascript" src="'.BUILDERUX_DIR_URL .'/assets/js/builderux.min.js"></script>';
	$scripts.='<script type="text/javascript" src="'.BUILDERUX_DIR_URL.'/assets/js/byuilderux_daynamic_js.php"></script>';
	// some inline variables 
	$activetheme = get_option('bx_active_theme');
	if(isset($activetheme) && $activetheme != 'default'){
		//wp_enqueue_style( $activetheme.'_css', BUILDERUX_DIR_URL .'/frontend/views/themes/'.$activetheme.'/assets/css/custom.css');
		$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/frontend/views/themes/'.$activetheme.'/assets/css/custom.css"></link>';
	}	
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/common/assets/css/bootstrap.min.css"></link>';
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL.'/assets/css/byuilderux_daynamic_css.php"></link>';
	$scripts.='<link rel="stylesheet" type="text/css" href="'.BUILDERUX_DIR_URL .'/assets/css/builderux_customerportal.css"></link>';
	return $scripts;
}
function is_Valid_step($slug){
	switch($slug){
		case 'plan': 
			if(isset($_REQUEST['subdiv'])){
				return true;
			}else{
				return false;
			}
		break;
		
		case 'unit': 
			if(isset($_GET['subdiv']) && isset($_GET['plan']))
			{
				return true;
			}else{
				return false;
			}
		break;
		
		case 'ehome': 
			if(isset($_REQUEST['subdiv']) && isset($_REQUEST['plan'])){
				return true;
			}elseif(isset($_GET['CommunicationID'])){
				return true;
			}else{
				return false;
			}
		break;
		
		case 'options': 
			if(isset($_REQUEST['subdiv']) && isset($_REQUEST['plan']) && isset($_REQUEST['_lotid']) && isset($_REQUEST['phaseID']))
				return true;
			else
				return false;
		break;
		
		default:
			return true;
		break;	
		
	}
	
	
}
function Builderux_legends(){
	$status=array('Available'=>'Available','Customer'=>'Reserved','Sold'=>'Sold','Spec'=>'Spec','Unreleased'=>'Unreleased','Model'=>'Model','Closed'=>'Closed');
	$optionbuynow=get_option('builderux_BuyNow_setting');
	$html="<ul class='bx_legands_items'>";
	foreach($status as $key=>$statusname){
		$lot_image_default = isset($optionbuynow['images_buy_config']['topoImage'.str_replace(' ', '', $statusname)])?$optionbuynow['images_buy_config']['topoImage'.str_replace(' ', '', $statusname)]:'';
		if(!empty($lot_image_default))
			$style="style='background-image:url({$lot_image_default})'";
		else	
			$style='';
			$html.="<li class='bx_legands_item'>
						<span class='lgnd_icon'>
							<i class='lot_{$key} topo_icon' $style></i>
						</span>
						<span class='lgnd_txt'>{$statusname}</span>
					</li>";
	}
	$html.="</ul>";
	return $html;
}
function BuilderUX_Submit_Reviewlead($reviewid){
	$isSubmit=SubmitReviewToSS($reviewid);
	if($isSubmit===true)
		wp_redirect(add_query_arg(array('action'=>'reviewlist'),get_permalink()));
	else
		echo $isSubmit;
}
/*
 * 
 * name: SubmitReviewToSS
 * @param int $reviewid
 * @return
 * 
 */
function SubmitReviewToSS($reviewid){
	if($reviewid){
		$review=get_bxdata_byID('builder_choosehome_request','ID',$reviewid);
		if(!$review)
			return builderUX_flash('danger',"This record is invalid . Please try again.");
		
		
		if(!class_exists('SoapClient')){
			return builderUX_flash('danger',"Class <b>SoapClient</b> not found on this server.Please enable it.");
		}
		$options=get_option('builderux_BuyNow_setting');
		//$wsdl="http://dev1.digitaltransfusion.net/Elead.asmx?op=SubmitEleadXML&wsdl";
		$wsdl=isset($options['topo_ss_wsdl']) ? $options['topo_ss_wsdl'] : '';
		$sGUID=isset($options['topo_ss_guid']) ? $options['topo_ss_guid'] : '';
		$buildername=isset($options['topo_ss_builder']) ? $options['topo_ss_builder'] : '';
		if(!$wsdl){
			return builderUX_flash('danger',"Error to submit the lead. Error: WSDL link not found . Please contact Admin");
		}
		$userinfo=get_userdata($review->user_id);
		// user details
		$fname=isset($userinfo->first_name) ? $userinfo->first_name : '';
		$lname=isset($userinfo->last_name) ? $userinfo->last_name : '';
		$email=isset($userinfo->user_email) ? $userinfo->user_email : '';
		
		global $wpdb;
		$prefix=$wpdb->prefix;
		
		$sql="SELECT mp.PlanModelNum FROM `{$prefix}builder_choosehome_request` h LEFT JOIN {$prefix}builder_phaseplan p ON h.`planid`=p.ID LEFT JOIN {$prefix}builder_masterplan mp ON mp.ID=p.MasterPlanID where h.id=1";
		
		$data=$wpdb->get_row($sql);
		// lot details
		$lotdetail=get_bxdata_byID('builder_phaselot','ID',$review->unitid);
		$JobUnitNum=isset($lotdetail->JobUnitNum) ? $lotdetail->JobUnitNum: '';
		$subdivisionid=$review->subdivisionid;
		$PlanModelNum=isset($data->PlanModelNum) ? $data->PlanModelNum : '';
		// phase detail
		$phasedetail=get_bxdata_byID('builder_phase','ID',$review->phaseID);
		$phasename=isset($phasedetail->PhaseName) ? $phasedetail->PhaseName: '';
		$selectedoptions=json_decode($review->options);
		$scenario_name=isset($review->scenario_name) ? $review->scenario_name : '';
		$date=date('m/d/Y h:i:s A');
		$xml='';
		if (getenv('HTTP_CLIENT_IP')) $ipaddress = getenv('HTTP_CLIENT_IP');
		else
		if (getenv('HTTP_X_FORWARDED_FOR')) $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else
		if (getenv('HTTP_X_FORWARDED')) $ipaddress = getenv('HTTP_X_FORWARDED');
		else
		if (getenv('HTTP_FORWARDED_FOR')) $ipaddress = getenv('HTTP_FORWARDED_FOR');
		else
		if (getenv('HTTP_FORWARDED')) $ipaddress = getenv('HTTP_FORWARDED');
		else
		if (getenv('REMOTE_ADDR')) $ipaddress = getenv('REMOTE_ADDR');
		else $ipaddress = 'UNKNOWN';
		$xml.='<?xml version="1.0" encoding="utf-8"?>
			<RootNode>
			 <Lead>
			  <Contact FirstName="'.$fname.'" LastName="'.$lname.'" Email="'.$email.'" Phone="" StreetAddress="" StreetAddress2="" City="" State="" Country="" PostalCode="" VisitDate="'.$date.'" ContactType="" SendResponse="" IsWebLead="" IPAddress="'.$ipaddress.'" WorkPhone="" ScenarioName="'.$scenario_name.'" WorkPhoneExt="" MobilePhone="" Fax="" Pager="" Title="" UserName="support" Rank="" TwitterID="" TwitterName="" AllowBeBacks="" ProcessAllScenarios="" UpdateAllDemographics="" DontUpdateBeBackDemos="" ValidateByEmail="" SitesDefaultAddress="" CoFirstName="" CoTitle="" CoLastName="" CoEmail="" CoPhone="" CoWorkPhone="" CoFax="" CoPager="" CoMobilePhone="" CoStreetAddress="" CoStreetAddress2="" CoCity="" CoState="" CoPostalCode="" CoCountry="" />
			  <Qualifications Comments="" Note="" />
			  <PropertyInterest BuilderName="'.$buildername.'" MasterCommunity="" CommunityNumber="" CommunityName="abc1231231" PlanNumber="" SpecNumber="" SpecAddress="" />
			  <Selections>
			   <Lot>
				<GoToContract>False</GoToContract>
				<JobUnitNum>'.$JobUnitNum.'</JobUnitNum>
				<Phase>'.$phasename.'</Phase>
				<PlanModelNum>'.$PlanModelNum.'</PlanModelNum>
				<SubdivisionID>'.$subdivisionid.'</SubdivisionID>
			   </Lot>
			   <Options>';
			   foreach($selectedoptions as $key=>$option){
				   $optid=isset($option->id) ? $option->id : 0;
				   $optiondata=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."builder_phaseplanoption where ID={$optid}");
				   $OptionCode=isset($optiondata->OptionCode) ? $optiondata->OptionCode : '';
				   $OptionLongDesc=isset($optiondata->OptionLongDesc) ? $optiondata->OptionLongDesc : $option->desc;
				   $attributes=json_decode(stripslashes($option->attributes)); 
				   $xml.='<Option>
					 <IsCustom>False</IsCustom>
					 <Code>'.$OptionCode.'</Code>
					 <ShortDesc>'.$option->desc.'</ShortDesc>
					 <LongDesc>'.$OptionLongDesc.'</LongDesc>
					 <Qty>'.$option->count.'</Qty>
					 <Price>'.$option->price.'</Price>';
					 $xml.="<Attributes>";
						if(!empty($attributes)){
							foreach($attributes as $key=>$attr){
								$attrbutedata=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."builder_attributes where attribute_id={$attr->attribute}");
								$ItemID=isset($attrbutedata->ItemID) ? $attrbutedata->ItemID : '';
								$GroupID=isset($attrbutedata->GroupID) ? $attrbutedata->GroupID : '';
								$ItemGroupID=isset($attrbutedata->ItemGroupID) ? $attrbutedata->ItemGroupID : '';
								$Question=isset($attrbutedata->Question) ? $attrbutedata->Question : '';
								$answer=isset($attr->answer) ? $attr->answer : '';
								$xml.="<Attribute TextData='' Price='{$option->price}' GroupName='{$Question}' AttributeName='{$answer}' GroupID='{$GroupID}' ItemID='{$ItemID}' ItemGroupID='{$ItemGroupID}'></Attribute>"; 
							}
						}
						$xml.="</Attributes>";	
					$xml.='</Option>';
				}
				$xml.='</Options>
			   <ContractDocumentDescription>BuilderUX Choose Home '.$date.'</ContractDocumentDescription>
			  </Selections>
			 </Lead>
			</RootNode>
			';	
		try{
			 $options = array(
                'soap_version'=>SOAP_1_2,
                'exceptions'=>true,
                'trace'=>1,
                'cache_wsdl'=>WSDL_CACHE_NONE
            );
			$client = new SoapClient($wsdl);
			$result = $client->SubmitEleadXML(array(
				'sGUID' => $sGUID,
				'xml' => $xml
			));
	
			return true;
		}catch(Exception $e){
			$message=$e->getMessage();
			return builderUX_flash('danger',"Error to submit the lead. Error: {$message}");
			die;
		}
	}else{
		return builderUX_flash('danger',"Your request is envalid please try again");
	}
	
}
/*
 * 
 * name: Bx_get_optionimage
 * @param array $options
 * @return string
 * 
 */
function Bx_get_optionimage($options){
	if(isset($options->id)){
		global $wpdb;
		$table=$wpdb->prefix.'builder_phaseplanoption';
		$optionimg=$wpdb->get_var("SELECT ImageURL FROM {$table} where ID={$options->id}");
		if($optionimg)
			return "<a class='fancyme' href='$optionimg'><img src='$optionimg'></a>";
		else
			return "<img src='".BUILDERUX_PLACEHOLDER."'>";	
	}else{
		return "<img src='".BUILDERUX_PLACEHOLDER."'>";
	}
}
function bx_PlanSearchQuery($postdata){
	//echo "<pre>"; print_r($postdata);
	global $wpdb;
	$prefix=$wpdb->prefix;
	
	$sql=""; $datavals=array();
	if(isset($postdata['bedroom_filter'])){
		$bedroom=$postdata['bedroom_filter'];
		foreach($bedroom as $key=>$value){
			if($value!='all')
				$datavals[]="{$prefix}builder_masterplan.bedrooms='{$value}'";
		}
		if(!empty($datavals))
			$sql.=' and ('.implode(' or ',$datavals).')';
	}
	$datavals=array();
	if(isset($postdata['bath_filter'])){
		$bedroom=$postdata['bath_filter'];
		foreach($bedroom as $key=>$value){
			if($value!='all')
				$datavals[]="{$prefix}builder_masterplan.bathrooms='{$value}'";
		}
		if(!empty($datavals))
			$sql.=' and ('.implode(' or ',$datavals).')';
	}
	$datavals=array();
	if(isset($postdata['squareft'])){
		$squarft=$postdata['squareft'];
		foreach($squarft as $key=>$value){
			$rabge=explode('-',$value);
			if(isset($rabge[0]) && isset($rabge[1])){
				$from=$rabge[0]; $to=$rabge[1];
				$datavals[]="{$prefix}builder_masterplan.squarefeet BETWEEN {$from} and {$to}";
			}elseif(!isset($rabge[1])){
				$from=$rabge[0]; 
				if($from!='all'){
				$datavals[]="{$prefix}builder_masterplan.squarefeet >= {$from}";
			   }
			}
			
		}
		if(!empty($datavals))
			$sql.=' and ('.implode(' or ',$datavals).')';
	}
	
	return $sql;	
}

function Bux_moveinready_LotDetail($lotid){
	global $wpdb;
	$prefix=$wpdb->prefix; 
		$query = "SELECT topo.*, lot.*, pp.*,mp.*,ss.SubdivisionNum,ss.MarketingName as sub_marketingname, lot.ID as lotid, mp.ID as masterplan FROM {$prefix}builder_topoxycoord topo 
		LEFT Join {$prefix}builder_phaselot lot on topo.LotID = lot.ID
		LEFT JOIN {$prefix}builder_phaseplan pp ON pp.ID=lot.PhasePlanID
		LEFT JOIN {$prefix}builder_masterplan mp ON pp.MasterPlanID=mp.ID
		LEFT JOIN {$prefix}builder_subdivision ss ON lot.SubdivisioNID=ss.ID
		WHERE lot.id='{$lotid}'";
	$lotdata = $wpdb->get_row($query);
	if(empty($lotdata))
		return builderUX_flash('danger',"No data available to display.");
	$lotimg=Bx_lot_image($lotdata);
	return get_bx_template('LotDetail',array('lotdata'=>$lotdata,'lotimg'=>$lotimg));
}
/*
 * 
 * name: BuilderuxErrorLog
 * @param
 * @return email 
 * 
 */
function BuilderuxErrorLog($subject="exception",$logs=""){
	echo "fgdg:";
	$option=get_option('builderux_BuyNow_setting');
	echo "<pre>"; print_r($option);
	$emailaddress="hc@builderux.com,kgill@wadejurneyhomes.com";
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	//$ismailsent=wp_mail($emailaddress,$subject,$logs,$headers);
	die;
}
function Bux_moveinready_BuilderuxLotDetail($lotid){
	 wp_enqueue_script( 'bx-slider-js' );
	 wp_enqueue_style( 'bx-slider-css' );
	
	global $wpdb;
	$prefix=$wpdb->prefix; 
	$query = "SELECT lot.*, pp.*,mp.*,ss.SubdivisionNum,ss.MarketingName as sub_marketingname, lot.ID as lotid, mp.ID as masterplan FROM {$prefix}builder_phaselot lot 
		
		LEFT JOIN {$prefix}builder_phaseplan pp ON pp.ID=lot.PhasePlanID
		LEFT JOIN {$prefix}builder_masterplan mp ON pp.MasterPlanID=mp.ID
		LEFT JOIN {$prefix}builder_subdivision ss ON lot.SubdivisioNID=ss.ID
		WHERE lot.id='{$lotid}'";
	$lotdata = $wpdb->get_row($query);
	
	if(empty($lotdata))
		return builderUX_flash('danger',"No data available to display.");
	else
		return $lotdata;
	
}
function Bux_backurl_BuilderuxLotDetail(){
	 $actual_link = isset($_SERVER['HTTP_REFERER'])? $_SERVER['HTTP_REFERER']:'';
	

	if(isset($_GET['action']) && $_GET['action']=='lotdetail' ){
		if(!empty($actual_link)){
			unset($_SESSION["Back_pageurl"]);
			 $_SESSION['Back_pageurl']=$actual_link;
		}
	
	}else{
		unset($_SESSION["Back_pageurl"]);

	}
	return $result;

	
}
