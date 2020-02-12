<?php
/*
Template Name: CC Home
*/
global $cc_service_url, $cc_plugin_slug, $cc_plugin_title;

require_once(CC_PLUGIN_PATH . '/lib/Paginator.php');

$cc_config = get_option('cc_config');
$cc_initial_filter = $cc_config['initial_filter'];

$site_language = strtolower(get_bloginfo('language'));
$lang = substr($site_language,0,2);

$query = ( isset($_GET['s']) ? $_GET['s'] : $_GET['q'] );
$query = stripslashes($query);
$user_filter = stripslashes($_GET['filter']);
$page = ( isset($_GET['page']) ? $_GET['page'] : 1 );
$total = 0;
$count = 10;
$filter = '';

$cc_initial_filter = $_GET['country_code'];

if ($cc_initial_filter != ''){
    $cc_initial_filter = 'country_code:' . $cc_initial_filter;
    if ($user_filter != ''){
        $filter = $cc_initial_filter . ' AND ' . $user_filter;
    }else{
        $filter = $cc_initial_filter;
    }
}else{
    $filter = $user_filter;
}
$start = ($page * $count) - $count;

$cc_search = $cc_service_url . 'api/institution/search/?q=' . urlencode($query) . '&fq=' . urlencode($filter) . '&start=' . $start . '&lang=' . $lang;

if ( $user_filter != '' ) {
    $user_filter_list = preg_split("/ AND /", $user_filter);
    $applied_filter_list = array();
    foreach($user_filter_list as $filters){
        preg_match('/([a-z_]+):(.+)/',$filters, $filter_parts);
        if ($filter_parts){
            // convert to internal format
            $applied_filter_list[$filter_parts[1]][] = str_replace('"', '', $filter_parts[2]);
        }
    }
}

$response = @file_get_contents($cc_search);
if ($response){
    $response_json = json_decode($response);
    //var_dump($response_json);
    $total = $response_json->diaServerResponse[0]->response->numFound;
    $start = $response_json->diaServerResponse[0]->response->start;
    $center_list = $response_json->diaServerResponse[0]->response->docs;

    $type_list = $response_json->diaServerResponse[0]->facet_counts->facet_fields->institution_type;
    $thematic_list = $response_json->diaServerResponse[0]->facet_counts->facet_fields->institution_thematic;
    $country_list = $response_json->diaServerResponse[0]->facet_counts->facet_fields->country;
}

$page_url_params = real_site_url($cc_plugin_slug) . 'search?q=' . urlencode($query)  . '&filter=' . urlencode($filter);
$feed_url = real_site_url($cc_plugin_slug) . 'cc-feed?q=' . urlencode($query) . '&filter=' . urlencode($user_filter);

$pages = new Paginator($total, $start, $count);
$pages->paginate($page_url_params);

$home_url = isset($cc_config['home_url_' . $lang]) ? $cc_config['home_url_' . $lang] : real_site_url();
$plugin_breadcrumb = isset($cc_config['plugin_title_' . $lang]) ? $cc_config['plugin_title_' . $lang] : $cc_config['plugin_title'];

/* filters translations */
$type_translated['CoordinatingCentersRg'] = __('CoordinatingCentersRg','cc');
$type_translated['CoordinatingCentersNc'] = __('CoordinatingCentersNc','cc');
$type_translated['CooperatingCenters'] = __('CooperatingCenters','cc');
$type_translated['CooperatingCentersLILACS'] = __('CooperatingCentersLILACS','cc');
$type_translated['CooperatingCentersLEYES'] = __('CooperatingCentersLEYES','cc');
$type_translated['ParticipantsUnits'] = __('ParticipantsUnits','cc');
$type_translated['VHLNetwork'] = __('VHLNetwork','cc');

$thematic_translated['MedCarib'] = __('MedCarib','cc');
$thematic_translated['Nursing'] = __('Nursing','cc');
$thematic_translated['Border'] = __('Border','cc');
$thematic_translated['Disastres'] = __('Disastres','cc');
$thematic_translated['Psychology'] = __('Psychology','cc');
$thematic_translated['MTCI'] = __('MTCI','cc');

$filter_title_translated['institution_type'] = __('VHL Network', 'cc');
$filter_title_translated['institution_thematic'] = __('Thematic Networks', 'cc');
$filter_title_translated['country'] = __('Country', 'cc');

?>

<?php include('header.php') ?>

<section class="container" id="main_container">
	<div class="row">
		<div class="col-12 col-md-8 col-lg-9">
            <h2 class="text-center">
                <?php
                    if ( isset($total) && strval($total) == 0) {
                       echo __('No results found','cc');
                   }elseif (strval($total) > 1) {
                       echo $total . ' ' . __('Institutions','cc');
                   }
                ?>
            </h2>

            <?php
            foreach ( $center_list as $resource) {
                echo '<article>';
                echo '<div class="box1">';
                echo '<strong>' . $resource->title . '<br/>';
                if ($resource->unit){
                    foreach ( $resource->unit as $unit ){
                        echo $unit . '<br/>';
                    }
                }
                echo '</strong>';

                echo $resource->cooperative_center_code . '<br/>';
                if ($resource->institution_type){
                    $exclude_common_types = array('CooperatingCenters', 'ParticipantsUnits', 'VHLNetwork');
                    foreach ( $resource->institution_type as $type ){
                        if ( !in_array($type, $exclude_common_types) ){
                            echo $type_translated[$type] . '<br/>';
                        }
                    }
                }
                if ($resource->address){
                    echo '<p>' . $resource->address[0] . '<br/>';
                    echo $resource->city . ' - ' . $resource->state[0] . '- ' . get_lang_value($resource->country, $lang) . '</p>';
                }
                if ($resource->contact){
                    foreach ( $resource->contact as $contact ){
                        echo $contact . '<br/>';
                    }
                }

                echo '</div>';
                echo '</article>';
            }
            ?>
            <hr>
            <?php echo $pages->display_pages(); ?>

        </div>
        <div class="col-md-4 col-lg-3" id="filterRight">
            <div class="boxFilter">
                <?php if ($applied_filter_list) :?>
                    <form method="get" name="searchFilter" id="formFilters" action="<?php echo real_site_url($cc_plugin_slug); ?>search">
                        <input type="hidden" name="lang" id="lang" value="<?php echo $lang; ?>">
                        <input type="hidden" name="q" id="query" value="<?php echo $query; ?>" >
                        <input type="hidden" name="filter" id="filter" value="" >

                        <?php foreach ( $applied_filter_list as $filter => $filter_values ) :?>
                            <h4><?php echo $filter_title_translated[$filter]; ?></h4>
                            <ul>
                            <?php foreach ( $filter_values as $value ) :?>
                                <input type="hidden" name="apply_filter" class="apply_filter"
                                        id="<?php echo md5($value) ?>" value='<?php echo $filter . ':"' . $value . '"'; ?>' >
                                <li>
                                    <span class="filter-item">
                                        <?php
                                            if ($filter == 'country'){
                                                echo print_lang_value($value, $site_language);
                                            }elseif ($filter == 'institution_type'){
                                                echo $type_translated[$value];
                                            }elseif ($filter == 'institution_thematic'){
                                                echo $thematic_translated[$value];
                                            }else{
                                                echo $value;
                                            }
                                        ?>
                                    </span>
                                    <span class="filter-item-del">
                                        <a href="javascript:remove_filter('<?php echo md5($value) ?>')">
                                            <img src="<?php echo CC_PLUGIN_URL; ?>template/images/del.png">
                                        </a>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>
                    </form>
                    <hr/>
                <?php endif; ?>

                <h3><?php _e('VHL Network','cc'); ?></h3>
                <ul>
                    <?php foreach ( $type_list as $type ) { ?>
                        <li class="cat-item">
                            <?php
                                $filter_link = '?';
                                if ($query != ''){
                                    $filter_link .= 'q=' . $query . '&';
                                }
                                $filter_link .= 'filter=institution_type:"' . $type[0] . '"';
                                if ($user_filter != ''){
                                    $filter_link .= ' AND ' . $user_filter ;
                                }
                            ?>
                            <a href='<?php echo $filter_link; ?>'><?php echo $type_translated[$type[0]]; ?></a>
                            <span class="cat-item-count">(<?php echo $type[1]; ?>)</span>
                        </li>
                    <?php } ?>
                </ul>

			    <h3><?php _e('Thematic Networks','cc'); ?></h3>
			   	<ul>
                    <?php foreach ( $thematic_list as $thematic) { ?>
                        <?php
                            $filter_link = '?';
                            if ($query != ''){
                                $filter_link .= 'q=' . $query . '&';
                            }
                            $filter_link .= 'filter=institution_thematic:"' . $thematic[0] . '"';
                            if ($user_filter != ''){
                                $filter_link .= ' AND ' . $user_filter ;
                            }
                        ?>
                        <li class="cat-item">
                            <a href='<?php echo $filter_link; ?>'><?php echo $thematic_translated[$thematic[0]] ?></a>
                            <span class="cat-item-count">(<?php echo $thematic[1] ?>)</span>
                        </li>
                      <?php } ?>
				</ul>

                <h3><?php _e('Country','cc'); ?></h3>
                <ul>
                    <?php foreach ( $country_list as $country ) { ?>
                        <?php
                            $filter_link = '?';
                            if ($query != ''){
                                $filter_link .= 'q=' . $query . '&';
                            }
                            $filter_link .= 'filter=country:"' . $country[0] . '"';
                            if ($user_filter != ''){
                                $filter_link .= ' AND ' . $user_filter ;
                            }
                        ?>
                        <li class="cat-item">
                            <a href='<?php echo $filter_link; ?>'><?php print_lang_value($country[0], $site_language)?></a>
                            <span class="cat-item-count">(<?php echo $country[1] ?>)</span>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php include('footer.php'); ?>