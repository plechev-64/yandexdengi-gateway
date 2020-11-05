<?php

add_action( 'rcl_payments_gateway_init', 'rcl_gateway_yandexdengi_init', 10 );
function rcl_gateway_yandexdengi_init() {
	rcl_gateway_register( 'yandexdengi', 'Rcl_Yandexdengi_Payment' );
}

class Rcl_Yandexdengi_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'codepro',
			'name'		 => 'Яндекс.Деньги',
			'submit'	 => __( 'Оплатить через Яндекс.Деньги' ),
			'icon'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'	 => 'text',
				'slug'	 => 'yd_account',
				'title'	 => __( 'Номер счета в Яндекс.Деньги' )
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'yd_secret',
				'title'	 => __( 'Секретный код' )
			),
			array(
				'type'	 => 'checkbox',
				'slug'	 => 'yd_methods',
				'title'	 => __( 'Способы оплаты' ),
				'values' => array(
					'PC' => __( 'С кошелька Яндекс.Деньги' ),
					'AC' => __( 'С банковской карты' ),
					'MC' => __( 'Мобильный платеж (не рекомендуется)' )
				)
			)
		);
	}

	function get_form( $data ) {

		$yd_account	 = rcl_get_commerce_option( 'yd_account' );
		$methods	 = rcl_get_commerce_option( 'yd_methods', array( 'PC', 'AC', 'MC' ) );

		if ( ! $methods )
			return false;

		$fields = array(
			'receiver'		 => $yd_account,
			'successURL'	 => get_permalink( $data->page_successfully ),
			'failUrl'		 => get_permalink( $data->page_fail ),
			'targets'		 => $data->description,
			'label'			 => implode( ':', array( $data->pay_id, $data->pay_type, $data->user_id ) ),
			'quickpay-form'	 => 'shop',
			'need-fio'		 => 'false',
			'need-email'	 => 'false',
			'need-phone'	 => 'false',
			'need-address'	 => 'false',
			'sum'			 => number_format( $data->pay_summ, 2, '.', '' )
		);

		$nms = array(
			'PC' => [
				__( 'Кошелек Яндекс.Деньги' ),
				rcl_addon_url( 'assets/1.png', __FILE__ )
			],
			'AC' => [
				__( 'Банковская карта' ),
				rcl_addon_url( 'assets/2.png', __FILE__ )
			],
			'MC' => [
				__( 'Мобильный платеж' ),
				rcl_addon_url( 'assets/3.png', __FILE__ )
			]
		);

		$values		 = array();
		$first_key	 = false;
		$styles		 = '<style>#rcl-field-paymentType .rcl-field-core .block-label {
							padding: 10px 10px 10px 40px;
							background-size: 30px;
							background-position: top 5px left 5px;
							background-repeat: no-repeat;
							border: 1px solid #ccc;
							font-size: 14px;
							width: 100%; display:block;
						}</style>';
		foreach ( $methods as $k => $method ) {

			if ( ! $k )
				$first_key = $method;

			$values[$method] = $nms[$method][0];

			$styles .= '<style>#rcl-field-paymentType .rcl-radio-box[data-value="' . $method . '"] .block-label{background-image:url(' . $nms[$method][1] . ');}</style>';
		}

		$fields[] = array(
			'type'		 => 'radio',
			'slug'		 => 'paymentType',
			'display'	 => 'block',
			'title'		 => __( 'Выберите способ оплаты' ),
			'default'	 => $first_key,
			'values'	 => $values
		);

		return parent::construct_form( array(
				'action' => 'https://money.yandex.ru/quickpay/confirm.xml',
				'fields' => $fields
			) ) . $styles;
	}

	function result( $data ) {

		$yd_account	 = rcl_get_commerce_option( 'yd_account' );
		$yd_secret	 = rcl_get_commerce_option( 'yd_secret' );

		$hash = sha1( implode( '&', array(
			$_POST['notification_type'],
			$_POST['operation_id'],
			$_POST['amount'],
			$_POST['currency'],
			$_POST['datetime'],
			$_POST['sender'],
			$_POST['codepro'],
			$yd_secret,
			$_POST['label']
			) ) );

		$label = explode( ':', $_REQUEST["label"] );

		if ( strtolower( $hash ) == strtolower( $_POST['sha1_hash'] ) ) {
			if ( ! parent::get_payment( $label[0] ) ) {
				parent::insert_payment( array(
					'pay_id'	 => $label[0],
					'pay_summ'	 => $_REQUEST["withdraw_amount"],
					'user_id'	 => $label[2],
					'pay_type'	 => $label[1]
				) );
				echo 'OK';
				exit;
			}
		} else {
			rcl_mail_payment_error( $hash );
		}
	}

	function success( $process ) {

		$label = explode( ':', $_REQUEST["label"] );

		if ( parent::get_payment( $label[0] ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
