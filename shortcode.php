<?php
add_shortcode( 'builderux_choose_home', 'Builderux_Choose_Home' );
add_shortcode( 'builderux_subdivisions', 'get_where_we_build_func' );
add_shortcode( 'builderux_flooplans', 'get_flooplan_func' );
add_shortcode( 'builderux_topo_reservenow', 'builderux_topo_reservenow' );
add_shortcode( 'builderux_topo_reservernow', 'builderux_topo_reservenow' );
add_shortcode( 'builderux_moveinready_homes', 'get_moveinready_house_func' );
add_shortcode( 'builderux-model-homes', 'builderux_model_house_func' );
add_shortcode( 'builderux-User', 'builderux_user_func' );
add_shortcode( 'flexplandemo', 'Builderux_Flex_data' );
/*
 * 
 * name: Builderux_Choose_Home
 * @param
 * @return
 * 
 */
function Builderux_Choose_Home(){
	$review='';
		$html='<div id="bx_choose_home" class="bx_choosehome_wrap">';
			// get the steps 
			if(isset($_GET['err']) && $_GET['err']=='invailid')
				$html .= builderUX_flash('danger',"Please Start at Step First");
			if(isset($_GET['action']) && $_GET['action']=='iPanorama'){
				$pid=isset($_GET['iPanorama']) ? $_GET['iPanorama'] : null;
				echo do_shortcode("[ipanorama id='{$pid}']");
			}else{	
				$html .= BuilderuX_Steps();
				add_action('wp_footer','bx_plan_search_form');
				// get the content area
				$html.='<div id="bx_choose_home_content" class="bx_choose_home_content">';
					$html .= '<section class="lists">';
						$html.='<div class="">';
							if(isset($_GET['action']) && $_GET['action']=='reviewlist'){
								if(isset($_POST['copy_review'])){
									global $current_user; 
									wp_get_current_user();
									if(isset($_REQUEST['user']) && !empty($_REQUEST['user']))
										$user_id=$_REQUEST['user'];
									else
										$user_id=$current_user->ID;
									
									if(!$current_user){
										$html.=builderUX_flash('danger',"You need to login first");
									}else{
										$reviewid=$_POST['review_id'];
										$review=builderux_copy_scenario($reviewid,$user_id);
										$_POST=array();
									}
								}elseif(isset($_POST['finalize_review'])){
									$postdata=$_REQUEST;
									$html.=BuilderUx_finalize_review($postdata);
								}
								$html.=BuilderUx_reviewlist($review);
							}elseif((isset($_GET['usertype']) && $_GET['usertype']=='postcontract') && (isset($_GET['review_id']) && !empty($_GET['review_id']))){
								$html.=BuilderUx_handel_PostContract();
							}elseif((isset($_GET['action']) && $_GET['action']=='submitsslead') && isset($_GET['reviewid'])){
								$reveiwid=$_GET['reviewid'];
								BuilderUX_Submit_Reviewlead($reveiwid);	
							}else
								$html.=BuilderUx_home_Content();
						$html .= '</div>';
					$html.='</section>';
				// $html .= bux_pagination(bx_totalrecords('builder_subdivision'));
				$html .= '</div>';
			}
		$html.='</div>';
	return $html;
}

/*
 * 
 * name: get_moveinready_house_func
 * @param
 * @return string
 * 
 */
function get_moveinready_house_func($arg=array()){
	$html=''; global $wpdb; $prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
	if(isset($_REQUEST['action'])){
		switch($_REQUEST['action']){
			case 'detail':
				$masterplan=$_REQUEST['masterplan'];
				$lot=$_REQUEST['lot'];
				return Bux_moveinready_single($masterplan,$lot);
			break;
			case 'lotdetail':
				wp_enqueue_script( 'bx-slider-js' );
				wp_enqueue_style( 'bx-slider-css' );
				$LotId=$_REQUEST['LotId'];
				return Bux_moveinready_LotDetail($LotId);
			break;
			default:
				return builderUX_flash('danger',"Invalid action selected");
			break;
		}
	}else{
		if(isset($arg['subdivision']))
		{	
			$subdivision=$arg['subdivision'];
			$sql="select * from {$prefix}builder_subdivision where 	SubdivisionNum LIKE '".$subdivision."'";
			$subdivisiondata=$wpdb->get_row($sql);
			if($subdivisiondata){
				$subdivisionid=$subdivisiondata->ID;
				//return Bux_moveinready_data();
				return Bux_moveinready_reserve($subdivisionid);
			}
		}else{	
			return Bux_moveinready_data();
		}
	}
	return $html;	
}

function builderux_model_house_func(){
	$html=''; global $wpdb; $prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
	if(isset($_REQUEST['action'])){
		switch($_REQUEST['action']){
			case 'detail':
				$masterplan=$_REQUEST['masterplan'];
				$lot=$_REQUEST['lot'];
				$html.=Bux_moveinready_single($masterplan,$lot);
			break;

			default:
				$html.=builderUX_flash('danger',"Invalid action selected");
			break;
		}
	}else{
		$html.=Bux_moveinready_data('Model');
	}
	return $html;
}
/*
 * 
 * name: get_where_we_build_func
 * @param
 * @return string
 * 
 */
function get_where_we_build_func()
{
	$html='';
	if(isset($_REQUEST['action'])){
		switch($_REQUEST['action']){
			case 'detail':
				$subdiv=$_REQUEST['subdivision'];
				$html.=BuilderUX_get_Subdivision($subdiv);
			break;
			case 'subdivision':
				$subdiv=$_REQUEST['_id'];
				$html.=BuilderUX_get_Subdivision($subdiv);
			break;
			case 'floorplans':
				$subdiv=$_REQUEST['subdivision'];
				$html.=BuilderUX_get_Plans($subdiv);
			break;
			case 'moveinready':
				if(isset($_GET['subdivision']) && !empty($_GET['subdivision'])){
					$subdiv=$_GET['subdivision'];
					$html.=Bux_moveinready_data('Spec',$subdiv);
				}
			break;
			case 'modelhouse':
				if(isset($_GET['subdivision']) && !empty($_GET['subdivision'])){
					$subdiv=$_GET['subdivision'];
					$html.=Bux_moveinready_data('Model',$subdiv);
				}
			break;
			case 'plandetail':
				$planid=$_REQUEST['planid'];
				$html.=BuilderUX_get_Plan($planid);
			break;
			case 'available':
				$masterplan=$_REQUEST['planid'];
				$lot=$_REQUEST['lot'];
				$html.=Bux_moveinready_single($masterplan,$lot);
			break;
			default:
				$html.=builderUX_flash('danger',"Invalid action selected");
			break;
			
			 
		}
	}else{
		$subdivisions=bx_fetchall('builder_subdivision');
		if(!empty($subdivisions))
			$html.=get_bx_template('where_we_build',array('subdivisions'=>$subdivisions));
		else
			$html.="<div class='bx_no_data'>There is no record to display</div>";
	}
	
	return $html;	
}

/*
 * 
 * name: get_flooplan_func
 * @param
 * @return string
 * 
 */
function get_flooplan_func(){
	$html=''; global $wpdb; $prefix=$wpdb->prefix; $limit=BUILDERUX_PAGE_LIMIT;
	if(isset($_REQUEST['action'])){
		switch($_REQUEST['action']){
			case 'plandetail':
				$planid=$_REQUEST['planid'];
				$html.=BuilderUX_get_Plan($planid);
			break;
			case 'subdivision':
				$subdiv=$_REQUEST['_id'];
				$html.=BuilderUX_get_Subdivision($subdiv);
			break;
			case '360view':
				if(isset($_GET['iPanorama']) && $_GET['iPanorama']){
					$pid=isset($_GET['iPanorama']) ? $_GET['iPanorama'] : null;
					echo do_shortcode("[ipanorama id='{$pid}']");
				}
			break;
			case 'floorplans':
				$subdiv=$_REQUEST['subdivision'];
				$html.=BuilderUX_get_Plans($subdiv);
			break;
			case 'available':
				$masterplan=$_REQUEST['planid'];
				$lot=$_REQUEST['lot'];
				$html.=Bux_moveinready_single($masterplan,$lot);
			break;
			default:
				$html.=builderUX_flash('danger',"Invalid action selected");
			break;
		}
	}else{
		$paged=isset($_REQUEST['bx_page']) ? $_REQUEST['bx_page'] : 1; 
		$offset=($paged-1)*$limit;
		$wheresql='';
		if(isset($_POST['bx_plan_search'])){
			$wheresql=bx_PlanSearchQuery($_POST);
		}
		$sql="Select Distinct {$prefix}builder_masterplan.*, max(plan.ID) as plan_id, att.FullURL, att.`AttachmentGroup` 
			from {$prefix}builder_masterplan
			Inner Join {$prefix}builder_phaseplan plan  on {$prefix}builder_masterplan.ID = plan.MasterPlanID 
			Inner join {$prefix}builder_masterplanattachment att on att.MasterPlanID={$prefix}builder_masterplan.ID 
			where att.AttachmentGroup='Website Elevation Image' $wheresql
			GROUP BY {$prefix}builder_masterplan.ID";
    
		// get count 
		$total = count($wpdb->get_results($sql));
		
		$sql.=" LIMIT {$offset},{$limit}";
		
		$plans=$wpdb->get_results($sql);
		add_action('wp_footer','bx_plan_search_form');
		if(!empty($plans))
			$html.=get_bx_template('floorplans',array('total'=>$total,'plans'=>$plans));
		else
			$html.="<div class='bx_no_data'>There is no record to display</div>";	
	}
	return $html;	
}

function bx_plan_search_form(){
	echo get_bx_template('plan_search_form');	
}

// added by himanshu
/**
* @Funct shortcode function to display reservation form
*
* @param array arguments
* @return int html
**/
function builderux_topo_reservenow($arg=array())
{
	$html='';
	// return if user wants to see the lot detail
	
	if(isset($_GET['action']) && $_GET['action']=='lotdetail'){
		return ;
	}
	if(isset($arg['subdivision']) || isset($arg[0]))
	{
		$action=isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$option=get_option('builderux_BuyNow_setting');
		switch($action)
		{
			
			default:
				$subdivision=isset($arg['subdivision']) ? $arg['subdivision'] : $arg[0];
				global $wpdb; $prefix=$wpdb->prefix;
				$sql="select * from {$prefix}builder_subdivision where 	SubdivisionNum LIKE '".$subdivision."'";
				$divison=$wpdb->get_row($sql);
				if(!$divison)
					wp_redirect(site_url());
						
				if((isset($arg['googlemap'])) || (isset($divison->isGoogleMap) && $divison->isGoogleMap==1)){
					if(isset($arg['googlemap']))
						$gid=$arg['googlemap'];
					elseif(isset($divison->google_map_id))
						$gid=$divison->google_map_id;
					else
						$gid='';
					$istest=isset($_GET['test']) ? $_GET['test'] : '';
					$islead=isset($_GET['SubmitLead']) ? $_GET['SubmitLead'] : '';		
					$html.="<script>
						function chooseNext(lotid,subdivision)
						{
							var nextpage='".add_query_arg(array('action'=>'reserve'),get_permalink( get_page_by_path( 'Builderux Reservation Process' )))."';
							nextpage+='&div_id='+subdivision+'&_lotid='+lotid;
							var istest='".$istest."';
							var issubmitlead='".$islead."';
							if(istest!='')
								nextpage+='&test='+istest;
							if(issubmitlead !='')	
								nextpage+='&SubmitLead='+issubmitlead;	
							window.location.href = nextpage;
							
						}
					</script>";
					$html.=do_shortcode("[bux_google_maps type='lot_button' id='{$gid}']");
				}else{
					//$topoimagepath=$wpdb->get_var("select 	FullURL from {$prefix}builder_topoimages where PhaseId is null and SubdivisionID=".$divison->ID);
					$topoimage=$wpdb->get_row("select * from {$prefix}builder_topoimages where PhaseId is null and SubdivisionID=".$divison->ID);
					if($topoimage){
						$topoimagepath=$topoimage->FullURL;
						//if image is not on local
						if(!$topoimage->isLocalFile)
							$topoimagepath=Bx_save_TopoImage($topoimage);
							
						$topoimagepath=Bx_Get_Image($topoimagepath,"full",time());
					}
					$html.=get_bx_template('reserve_plan',array('topoimagepath'=>$topoimagepath,'divison'=>$divison));
				}
			break;	
		}
	}else
	{
		$html.=builderUX_flash('danger',"Invalid Subdivision.");
	}
	return $html;
}
function Bx_save_TopoImage($topoimage){
	$url=$topoimage->FullURL;	
	$newurl=BX_uploadImageByUrl($url);
	if($newurl){
		global $wpdb; $prefix=$wpdb->prefix;
		$wpdb->update("{$prefix}builder_topoimages",array("FullURL"=>$newurl,"isLocalFile"=>1),array("img_id"=>$topoimage->img_id));
	}
	return $newurl;
}

/**
* Funct Reservation form submit
*
* param array $postdata ,  array files array 
* @return int Inserted idor false,
**/
function wpse_141088_upload_dir($dir)
{
	$certdir=BUILDERUX_JSON_UPLOAD.'/certificates/';
	if(!is_dir($certdir)){
		mkdir($certdir);
		$myfile = fopen($certdir.".htaccess", "w");
		fwrite($myfile, "deny from all");
		fclose($myfile);
	}
	return array(
		'path' => $certdir,
		'url' => BUILDERUX_JSON_UPLOAD_URL.'/certificates/',
	) + $dir;
}

function process_reservation_form($postdata,$files)
{
	if(!function_exists('wp_handle_upload')){
		require_once(ABSPATH . 'wp-admin/includes/file.php');
	}
	global $wpdb;
	$prefix=$wpdb->prefix;
	$datatosave=$postdata['TOPObuy'];
	$upload_overrides = array('test_form' => false);
	add_filter('upload_dir', 'wpse_141088_upload_dir');
	$movefile1=wp_handle_upload($files['preq_file'],$upload_overrides);
	$movefile2=wp_handle_upload($files['preq_id'],$upload_overrides);
	remove_filter('upload_dir', 'wpse_141088_upload_dir');
	$prequal=!isset($movefile1['error']) ? $movefile1['url']:'' ; // file url
	$prequal_id=!isset($movefile2['error']) ? $movefile2['url']:'' ; // file url
	
	$datatosave['prequal']=$prequal;
	$datatosave['prequal_id']=$prequal_id;
	$datatosave['created']=date('Y-m-d h:i:s');
	$datatosave['modified']=date('Y-m-d h:i:s');
	$lenders=isset($postdata['TOPObuy']['lenders']) ? serialize($postdata['TOPObuy']['lenders']) : '';
	$datatosave['lenders']=$lenders;
	$datainsert=$wpdb->insert( $prefix.'builder_reservation',$datatosave);
	
	if($datainsert)
		return $wpdb->insert_id;
	else
		return false;
}

/*
 * 
 * name: builderux_user_func
 * @param
 * @return
 * 
 */
function builderux_user_func(){
	$isuserlogin=get_current_user_id(); 
	$html='';
	$logouturl=wp_logout_url(get_permalink());
	if($isuserlogin){
		$option=get_option('builderux_home_setting');
		if(isset($option['isredirectlogin']) && isset($option['redirect_page_login']) && !empty($option['redirect_page_login']))
		{
			$redirecturl=get_permalink($option['redirect_page_login']);
			wp_redirect($redirecturl);
		}	
		$html.=builderUX_flash('success',"you are already logged in <a href='{$logouturl}'>Logout</a>");
	}else{
		$html.=get_bx_template('builderux_login_screen');
	}	
	return $html;	
}
?>
