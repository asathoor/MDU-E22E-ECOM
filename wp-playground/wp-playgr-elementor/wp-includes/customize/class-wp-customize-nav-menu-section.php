<?php
 class WP_Customize_Nav_Menu_Section extends WP_Customize_Section { public $type = 'nav_menu'; public function json() { $exported = parent::json(); $exported['menu_id'] = (int) preg_replace( '/^nav_menu\[(-?\d+)\]/', '$1', $this->id ); return $exported; } } 