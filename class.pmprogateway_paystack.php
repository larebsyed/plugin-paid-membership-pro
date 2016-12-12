<?php
/**
 * Plugin Name: Paystack - Paid Memberships Pro
 * Plugin URI: https://paystack.com
 * Description: Plugin to add Paystack payment gateway into Paid Memberships Pro
 * Version: 1.0
 * Author: Dat Pham
 * License: GPLv2 or later
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!function_exists('paystack_pmp_gateway_load')) {
	// gateway load
	add_action( 'plugins_loaded', 'paystack_pmp_gateway_load', 20);

	DEFINE('PAYSTACKPMP', "paystack-paidmembershipspro");

	function paystack_pmp_gateway_load() {
		// paid memberships pro required
		if (!class_exists('PMProGateway')) {
			return;
		}

		// load classes init method
		add_action('init', array('PMProGateway_paystack', 'init'));

		// plugin links
		add_filter('plugin_action_links', array('PMProGateway_paystack', 'plugin_action_links'), 10, 2 );

		if (!class_exists('PMProGateway_paystack')) {
			/**
			 * PMProGateway_paystack Class
			 *
			 * Handles Paystack integration.
			 *
			 */
			class PMProGateway_paystack extends PMProGateway {

				function __construct($gateway = NULL)
				{
					$this->gateway = $gateway;
					$this->gateway_environment =  pmpro_getOption("gateway_environment");

					if(!class_exists("PaystackConfig")) {
						require_once(dirname(__FILE__) . "/inc/class.paystack-config.php");
					}

					//set API connection vars
					PaystackConfig::setAPIUsername(pmpro_getOption("paystack_apiusername"));
					PaystackConfig::setAPIPassword(pmpro_getOption("paystack_apipassword"));
					PaystackConfig::setWalletID(pmpro_getOption("paystack_walletid"));
					PaystackConfig::setDirectkitURL(pmpro_getOption("paystack_directkiturl"));
					PaystackConfig::setWebkitURL(pmpro_getOption("paystack_webkiturl"));
					PaystackConfig::setDirectkitURLTest(pmpro_getOption("paystack_directkiturltest"));
					PaystackConfig::setWebkitURLTest(pmpro_getOption("paystack_webkiturltest"));
					PaystackConfig::setEnv(pmpro_getOption("gateway_environment"));
					PaystackConfig::setCSSURL(pmpro_getOption("paystack_cssurl"));

					return $this->gateway;
				}	

				/**
				 * Run on WP init
				 */
				static function init() {
					//make sure Paystack is a gateway option
					add_filter('pmpro_gateways', array('PMProGateway_paystack', 'pmpro_gateways'));
					
					//add fields to payment settings
					add_filter('pmpro_payment_options', array('PMProGateway_paystack', 'pmpro_payment_options'));
					add_filter('pmpro_payment_option_fields', array('PMProGateway_paystack', 'pmpro_payment_option_fields'), 10, 2);

					//code to add at checkout
					$gateway = pmpro_getGateway();
					if($gateway == "paystack")
					{
						add_filter('pmpro_include_billing_address_fields', '__return_false');
						add_filter('pmpro_required_billing_fields', array('PMProGateway_paystack', 'pmpro_required_billing_fields'));
						add_filter('pmpro_include_payment_information_fields', '__return_false');
						add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_paystack', 'pmpro_checkout_before_change_membership_level'), 10, 2);
						add_filter('pmpro_gateways_with_pending_status', array('PMProGateway_paystack', 'pmpro_gateways_with_pending_status'));
						add_filter('pmpro_pages_shortcode_checkout', array('PMProGateway_paystack', 'pmpro_pages_shortcode_checkout'), 20, 1);
						add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paystack', 'pmpro_checkout_default_submit_button'));
				// custom confirmation page
						add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_paystack', 'pmpro_pages_shortcode_confirmation'), 20, 1);
					}
				}

				/**
				 * Redirect Settings to PMPro settings
				 */
				static function plugin_action_links($links, $file) {
					static $this_plugin;

					if (false === isset($this_plugin) || true === empty($this_plugin)) {
						$this_plugin = plugin_basename(__FILE__);
					}

					if ($file == $this_plugin) {
						$settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__('Settings', PAYSTACKPMP).'</a>';
						array_unshift($links, $settings_link);
					}

					return $links;
				}
				static function pmpro_checkout_default_submit_button($show)
				{
					global $gateway, $pmpro_requirebilling;
					
					//show our submit buttons
					?>			
					<span id="pmpro_submit_span">
						<input type="hidden" name="submit-checkout" value="1" />		
						<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php if($pmpro_requirebilling) { _e('Check Out with Paystack', 'pmpro'); } else { _e('Submit and Confirm', 'pmpro');}?> &raquo;" />		
					</span>
					<?php
				
					//don't show the default
					return false;
				}
				/**
				 * Make sure Paystack is in the gateways list
				 */
				static function pmpro_gateways($gateways) {
					if(empty($gateways['paystack'])) {
						$gateways = array_slice($gateways, 0, 1) + array("paystack" => __('Paystack', PAYSTACKPMP)) + array_slice($gateways, 1);
					}
					return $gateways;
				}

				/**
				 * Get a list of payment options that the Paystack gateway needs/supports.
				 */
				static function getGatewayOptions() {
					$options = array (
						'paystack_tsk',
						'paystack_tpk',
						'paystack_lsk',
						'paystack_lpk',
						'gateway_environment',
						'currency',
						'tax_state',
						'tax_rate'
						);

					return $options;
				}

				/**
				 * Set payment options for payment settings page.
				 */
				static function pmpro_payment_options($options) {
					//get Paystack options
					$paystack_options = self::getGatewayOptions();

					//merge with others.
					$options = array_merge($paystack_options, $options);

					return $options;
				}

				/**
				 * Display fields for Paystack options.
				 */
				static function pmpro_payment_option_fields($values, $gateway) {
					?>
					<tr class="pmpro_settings_divider gateway gateway_paystack" <?php if($gateway != "paystack") { ?>style="display: none;"<?php } ?>>
						<td colspan="2">
							<?php _e('Paystack API Configuration', 'pmpro'); ?>
						</td>
					</tr>
					<tr class="gateway gateway_paystack" <?php if($gateway != "paystack") { ?>style="display: none;"<?php } ?>>
						<th scope="row" valign="top">
							<label for="paystack_tsk"><?php _e('Test Secret Key', 'pmpro');?>:</label>
						</th>
						<td>
							<input type="text" id="paystack_tsk" name="paystack_tsk" size="60" value="<?php echo esc_attr($values['paystack_tsk'])?>" />
						</td>
					</tr>
					<tr class="gateway gateway_paystack" <?php if($gateway != "paystack") { ?>style="display: none;"<?php } ?>>
						<th scope="row" valign="top">
							<label for="paystack_tpk"><?php _e('Test Public Key', 'pmpro');?>:</label>
						</th>
						<td>
							<input type="text" id="paystack_tpk" name="paystack_tpk" size="60" value="<?php echo esc_attr($values['paystack_tpk'])?>" />
						</td>
					</tr>
					<tr class="gateway gateway_paystack" <?php if($gateway != "paystack") { ?>style="display: none;"<?php } ?>>
						<th scope="row" valign="top">
							<label for="paystack_lsk"><?php _e('Live Secret Key', 'pmpro');?>:</label>
						</th>
						<td>
							<input type="text" id="paystack_lsk" name="paystack_lsk" size="60" value="<?php echo esc_attr($values['paystack_lsk'])?>" />
						</td>
					</tr>
					<tr class="gateway gateway_paystack" <?php if($gateway != "paystack") { ?>style="display: none;"<?php } ?>>
						<th scope="row" valign="top">
							<label for="paystack_lpk"><?php _e('Live Public Key', 'pmpro');?>:</label>
						</th>
						<td>
							<input type="text" id="paystack_lpk" name="paystack_lpk" size="60" value="<?php echo esc_attr($values['paystack_lpk'])?>" />
						</td>
					</tr>
					
					<?php
				}

				/**
				 * Remove required billing fields
				 */
				static function pmpro_required_billing_fields($fields)
				{
					unset($fields['bfirstname']);
					unset($fields['blastname']);
					unset($fields['baddress1']);
					unset($fields['bcity']);
					unset($fields['bstate']);
					unset($fields['bzipcode']);
					unset($fields['bphone']);
					unset($fields['bemail']);
					unset($fields['bcountry']);
					unset($fields['CardType']);
					unset($fields['AccountNumber']);
					unset($fields['ExpirationMonth']);
					unset($fields['ExpirationYear']);
					unset($fields['CVV']);

					return $fields;
				}

				static function pmpro_gateways_with_pending_status($gateways) {
					$morder = new MemberOrder();
					$found = $morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")));

					if((!in_array("paystack", $gateways)) && $found) {
						array_push($gateways,"paystack");
					} elseif(($key = array_search("paystack", $gateways)) !== false) {
						  unset($gateways[$key]);
					}

					return $gateways;
				}

				/**
				 * Instead of change membership levels, send users to Paystack payment page.
				 */
				static function pmpro_checkout_before_change_membership_level($user_id, $morder)
				{
					global $wpdb, $discount_code_id;
					
					//if no order, no need to pay
					if(empty($morder)) {
						return;
					}
					if(empty($morder->code))
						$morder->code = $morder->getRandomCode();	
						
					$morder->payment_type = "paystack";
					$morder->status = "pending";
					$morder->user_id = $user_id;
					$morder->saveOrder();

					//save discount code use
					if(!empty($discount_code_id))
						$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");

					$morder->Gateway->sendToPaystack($morder);
				}

				function sendToPaystack(&$order) {
					global $wp;

					$kit = new PaystackKit();
					
					$params = array();
					$amount = $order->PaymentAmount;
					$amount_tax = $order->getTaxForPrice($amount);			
					$amount = round((float)$amount + (float)$amount_tax, 2);			
			
					//call directkit to get Webkit Token
					$params = array('wkToken'=>$order->code,
						'wallet'=> PaystackConfig::getWalletID(),
						'amountTot'=>number_format(floatval($order->InitialPayment), 2, '.', ''),
						'amountCom'=>number_format(floatval($order->InitialPayment), 2, '.', ''),	// because money is transfered in merchant wallet
						'comment'=>'Paid Memberships Pro for '.$_SERVER["HTTP_HOST"]. ": Order N° " . $order->id,
						'returnUrl'=>urlencode(pmpro_url("confirmation", "?level=" . $order->membership_level->id)),
						'cancelUrl'=>urlencode(pmpro_url("checkout", "?level=" . $order->membership_level->id)),
						'errorUrl'=>urlencode(pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error")),
						'autoCommission'=>0,
						'registerCard'=>0, //For Atos //@TODO get value from payment form
						'useRegisteredCard'=>0, //For payline //@TODO get value from payment form
					);
					$amount = floatval($order->InitialPayment);			

					// echo pmpro_url("confirmation", "?level=" . $order->membership_level->id);
						// die();
						$mode = pmpro_getOption("gateway_environment");
						if ($mode == 'sandbox') {
							$key = pmpro_getOption("paystack_tsk");
						}else{
							$key = pmpro_getOption("paystack_lsk");

						}
						if ($key  == '') {
							echo "Api keys not set";
						}
						$txn_code = $txn.'_'.$order_id;

						$koboamount = $amount*100;
						
						$paystack_url = 'https://api.paystack.co/transaction/initialize';
						$headers = array(
							'Content-Type'	=> 'application/json',
							'Authorization' => "Bearer ".$key
						);
						//Create Plan
						$body = array(
							'email'	=> $order->Email,
							'amount' => $koboamount,
							'reference' => $order->code,
							'callback_url' => pmpro_url("confirmation", "?level=" . $order->membership_level->id)
							// 'metadata' => json_encode(array('custom_fields' => $meta )),

						);
						$args = array(
							'body'		=> json_encode( $body ),
							'headers'	=> $headers,
							'timeout'	=> 60
						);

						$request = wp_remote_post( $paystack_url, $args );
						// print_r($request);
						if( ! is_wp_error( $request )) {
							$paystack_response = json_decode(wp_remote_retrieve_body($request));
							$url	= $paystack_response->data->authorization_url;
							wp_redirect( $url );
							exit;
							
						}else{
							$order->Gateway->delete($order);
							wp_redirect(pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error=Failed"));
							exit();
						}
						exit;
				}

				static function pmpro_pages_shortcode_checkout($content) {
					$morder = new MemberOrder();
					$found = $morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")));
					if ($found) {
						$morder->Gateway->delete($morder);
					}
					
					if (isset($_REQUEST['error'])) {
						global $pmpro_msg, $pmpro_msgt;

						$pmpro_msg = __("IMPORTANT: Something went wrong during the payment. Please try again later or contact the site owner to fix this issue.<br/>" . urldecode($_REQUEST['error']), "pmpro");
						$pmpro_msgt = "pmpro_error";

						$content = "<div id='pmpro_message' class='pmpro_message ". $pmpro_msgt . "'>" . $pmpro_msg . "</div>" . $content;
					}

					return $content;
				}

				/**
				 * Custom confirmation page
				 */
				static function pmpro_pages_shortcode_confirmation($content) {
					global $wpdb, $current_user, $pmpro_invoice, $pmpro_currency,$gateway;
					if (!isset($_REQUEST['trxref'])) {
						$_REQUEST['trxref'] = null;
					}
					
					if (empty($pmpro_invoice))
					{
						$morder = new MemberOrder();
						$morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending", "success")));
						if (!empty($morder) && $morder->gateway == "paystack") $pmpro_invoice = $morder;
					}
						
					if (!empty($pmpro_invoice) && $pmpro_invoice->gateway == "paystack" && isset($pmpro_invoice->total) && $pmpro_invoice->total > 0)
					{
						$morder = $pmpro_invoice;
							// echo $morder->code.' - '.$_REQUEST['trxref'];
							if ($morder->code == $_REQUEST['trxref']) {
								$mode = pmpro_getOption("gateway_environment");
								if ($mode == 'sandbox') {
									$key = pmpro_getOption("paystack_tsk");
								}else{
									$key = pmpro_getOption("paystack_lsk");

								}
								$paystack_url = 'https://api.paystack.co/transaction/verify/' . $_REQUEST['trxref'];
								$headers = array(
									'Authorization' => 'Bearer ' . $key
								);
								$args = array(
									'headers'	=> $headers,
									'timeout'	=> 60
								);
								$request = wp_remote_get( $paystack_url, $args );
								if( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {
									$paystack_response = json_decode( wp_remote_retrieve_body( $request ) );
									if ( 'success' == $paystack_response->data->status ) {
										
									  	$pmpro_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . (int)$morder->membership_id . "' LIMIT 1");
										$pmpro_level = apply_filters("pmpro_checkout_level", $pmpro_level);
										$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time("mysql") . "'", $morder->user_id, $pmpro_level);
										$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time("mysql") . "'", $user_id, $pmpro_level);
										if (strlen($order->subscription_transaction_id) > 3) 
										{
											$enddate = "'" . date("Y-m-d", strtotime("+ " . $order->subscription_transaction_id, current_time("timestamp"))) . "'";
										}
										elseif (!empty($pmpro_level->expiration_number)) {
											$enddate = "'" . date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp"))) . "'";
										} else {
											$enddate = "NULL";
										}
										$custom_level = array(
												'user_id' 			=> $morder->user_id,
												'membership_id' 	=> $pmpro_level->id,
												'code_id' 			=> '',
												'initial_payment' 	=> $pmpro_level->initial_payment,
												'billing_amount' 	=> $pmpro_level->billing_amount,
												'cycle_number' 		=> $pmpro_level->cycle_number,
												'cycle_period' 		=> $pmpro_level->cycle_period,
												'billing_limit' 	=> $pmpro_level->billing_limit,
												'trial_amount' 		=> $pmpro_level->trial_amount,
												'trial_limit' 		=> $pmpro_level->trial_limit,
												'startdate' 		=> $startdate,
												'enddate' 			=> $enddate);

										if (pmpro_changeMembershipLevel($custom_level, $morder->user_id, 'changed')){
											$morder->status = "success";
											$morder->membership_id = $pmpro_level->id;
											$morder->payment_transaction_id	= $_REQUEST['trxref'];
											$morder->saveOrder();
										}

										//setup some values for the emails
										if (!empty($morder)) {
											$pmpro_invoice = new MemberOrder($morder->id);
										}else {
											$pmpro_invoice = NULL;
										}

										$current_user->membership_level = $pmpro_level; //make sure they have the right level info
										$current_user->membership_level->enddate = $enddate;
										if($current_user->ID) {
											$current_user->membership_level = pmpro_getMembershipLevelForUser($current_user->ID);
											// echo "interesting";
										}
										
										//send email to member
										// $pmproemail = new PMProEmail();
										// $pmproemail->sendCheckoutEmail($current_user, $invoice);

										// //send email to admin
										// $pmproemail = new PMProEmail();
										// $pmproemail->sendCheckoutAdminEmail($current_user, $invoice);
										// echo "<pre>";
										// print_r($pmpro_level);
										$content = "<ul>
											<li><strong>".__('Account', PAYSTACKPMP).":</strong> ".$current_user->display_name." (".$current_user->user_email.")</li>
											<li><strong>".__('Order', PAYSTACKPMP).":</strong> ".$pmpro_invoice->code."</li>
											<li><strong>".__('Membership Level', PAYSTACKPMP).":</strong> ".$pmpro_level->name."</li>
											<li><strong>".__('Amount Paid', PAYSTACKPMP).":</strong> ".$pmpro_invoice->total." ".$pmpro_currency."</li>
										  </ul>";
										ob_start();
										if(file_exists(get_stylesheet_directory() . "/paid-memberships-pro/pages/confirmation.php")) {
											include(get_stylesheet_directory() . "/paid-memberships-pro/pages/confirmation.php");
										}else{
											include(PMPRO_DIR . "/pages/confirmation.php");
										}
										
										$content .= ob_get_contents();
										ob_end_clean();
									}else{
										$content = 'Invalid Reference';
										
									}

								}else{
										$content = 'Unable to Verify Transaction';

								}
								
							}else{
								$content = 'Invalid Transaction Reference';
							}
					}
			
			
					return $content;
					
				}

				function delete(&$order) {
					//no matter what happens below, we're going to cancel the order in our system
					$order->updateStatus("cancelled");

					global $wpdb;
					$wpdb->query("DELETE FROM $wpdb->pmpro_membership_orders WHERE id = '" . $order->id . "'");
				}
			}
		}
	}
}
?>