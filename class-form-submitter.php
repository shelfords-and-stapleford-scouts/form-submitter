<?php
/*
+----------------------------------------------------------------------
| Copyright (c) 2018 Genome Research Ltd.
| This is part of the Wellcome Sanger Institute extensions to
| wordpress.
+----------------------------------------------------------------------
| This extension to Worpdress is free software: you can redistribute
| it and/or modify it under the terms of the GNU Lesser General Public
| License as published by the Free Software Foundation; either version
| 3 of the License, or (at your option) any later version.
|
| This program is distributed in the hope that it will be useful, but
| WITHOUT ANY WARRANTY; without even the implied warranty of
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
| Lesser General Public License for more details.
|
| You should have received a copy of the GNU Lesser General Public
| License along with this program. If not, see:
|     <http://www.gnu.org/licenses/>.
+----------------------------------------------------------------------
*/

class FormSubmitter { 
  var $code;
  var $session_string;
  var $defn;
  var $root_path;
  var $sequence;
  function __construct( ) {
    $this->sequence = 'aaaa';
    $root = array_key_exists( 'DOCUMENT_ROOT', $_SERVER )
          ? dirname( $_SERVER['DOCUMENT_ROOT'] )
          : dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    $this->root_path = implode( DIRECTORY_SEPARATOR, [
      $root,
      'data',
      'config',
      'forms'
    ] );
    if(function_exists( 'add_action' )) {
      add_action( 'wp_enqueue_scripts', array($this,'cssjs') );
    }
  }  
  function cssjs() {
    error_log("JS");
    wp_enqueue_style(  'formscss', '/wp-content/plugins/form-submitter/form.css',  [], null, false );
    wp_enqueue_script( 'jqueryx',   '/wp-content/plugins/form-submitter/jquery.js', [], null, true  );
    wp_enqueue_script( 'formsjs',  '/wp-content/plugins/form-submitter/form.js',   [], null, true  );
  }
  function set_code( $code ) {
    $this->code = preg_replace('/[^-\w]/','',$code );
    return $this;
  }
  function fetch( ) {
    $file = $this->root_path.DIRECTORY_SEPARATOR.$this->code.'.yaml';
    error_log( $file );
    if( file_exists( $file ) ) {
      $this->defn = yaml_parse_file( $file ); 
    } else {
      error_log( "Unable to open form definition file! ".$file );
      $this->defn = false;
    }
    return $this;
  }
  
  function render() {
    if( ! $this->defn ) {
      return '<p>Unable to find form with given code</p>';
    } 
    return '
<form method="post" action="/wp-content/plugins/form-submitter/form.php"
      class="fs-form"
      accept-charset="UTF-8"
      id="submitform"
      enctype="application/x-www-form-urlencoded" >
  <input type="hidden" name="code" value="'.$this->code.'" />
  <input type="hidden" name="session_code" value="" />
  <input type="hidden" name="fs_q" value="" />'.
      implode( '', array_map(
        [$this,'render_section'], $this->defn['sections']
      ) ).'
</form>';
  }
  
  function render_section( $defn ) {
    return '
  <section class="fs-section'.$this->get_page_class($defn).'"'.
      $this->render_logic( $defn ).'>
    '.
    ( array_key_exists( 'title', $defn ) ? '<h3>'.HTMLentities($defn['title']).'</h3>' : '' ).
      implode( '', array_map( [ $this, 'render_page' ], $defn['pages'] ) ).'
  </section>

';
  }

  function render_page( $defn ) {
    return '
    <div id="'.
      (array_key_exists('code',$defn)?$defn['code']:$this->next_code()).
      '" class="fs-page'.
      $this->get_page_class($defn).'"'.
      $this->render_logic( $defn ).'>
    '.implode( '', array_map( [ $this, 'render_block' ], $defn['defn'] ) ).'
    </div><!-- page -->

';
  }

  function render_block( $defn ) {
    if( $defn['type'] == 'group' ) {
      return '
      <div class="fs-group">'.
      implode( '', array_map( [ $this, 'render_block' ], $defn['defn'] ) ).'
      </div><!-- group -->';
    }
    $output = '
      <div class="fs-question'.
      $this->get_page_class($defn).'"'.
      $this->render_logic( $defn );
    if( array_key_exists( 'width', $defn ) ) {
      $output .= ' style="width: '.$defn['width'].'"';
    }
    $output .= '>';
    if( $defn['type'] == 'html' ) {
      $output .= '
        <div class="fs-intro">
          '.$this->render_intro( $defn['intro'] ).'
        </div><!-- intro -->';
    } elseif( $defn['type'] == 'heading' ) {
      $output .= '
        <h4>
          '.$this->render_intro( $defn['question'] ).'
        </h4>';
    } else {
      $code = array_key_exists( 'code', $defn ) ? $defn['code'] : $this->next_code();
      $input = '';
      if( array_key_exists( 'question', $defn ) ) {
        $input .= '
          <p><strong>'. HTMLentities( $defn['question'] ).'</strong></p>';
      }
      if( array_key_exists( 'intro', $defn ) ) {
        $input .= '
          <div class="fs-intro">
           '.$this->render_intro( $defn['intro'] ).'
          </div><!-- intro -->';
      }
      switch( $defn['type'] ) {
        case 'select':
        case 'country':
          $values = $defn['type'] == 'country' ? $this->countries() : $defn['values'];
          $output .= '
          <label>'.$input.'
            <select name="'.$code.'" id="'.$code.'">
              <option value="">-- select --</option>'.
          implode( '', array_map( function( $r ) {
            return '<option value="'.HTMLentities( is_array($r) ? $r[0] : $r ).'">'.
                  HTMLentities( is_array($r) ? $r[1] : $r ).'</option>';
          }, $values ) ). '
            </select>
          </label>';
        break;
        case 'checkbox':
        case 'radio':
        case 'yesno':
          if( $defn['type'] == 'yesno' ) {
            $defn['columns'] = 2;
            $defn['values']  = [['yes','Yes'],['no','No']];
            $type = 'radio';
          } else {
            $type= $defn['type'];
          }
          $extra = $defn['type'] == 'checkbox' ? '[]' : '';
          $x = 'aa';
          $output .= $input.'
        <ul class="fs-checkbox fs-columns-'.(array_key_exists('columns',$defn)?$defn['columns']:1).'">'.
          implode( '', array_map( function( $r ) use ($type,$extra,$code,$x) {
            return '
          <li><label><input type="'.$type.'" value="'.
            HTMLentities( is_array($r) ? $r[0] : $r ).'" id="'.
            $code.'_'.($x++).'" name="'.$code.$extra.'" /> '.
            HTMLentities( is_array($r) ? $r[1] : $r ).'</label></li>';
          }, $defn['values'] ) ).
          implode( '', array_map( function( $k ) use ($type,$extra,$code,$defn) {
            if( array_key_exists( $k, $defn ) && $defn[$k] ) {
              return '
            <li><label><input type="'.$type.'" value="'.$k.'" id="'.
              $code.'_'.$k.'" name="'.$code.$extra.'" /> '.ucfirst($k).'</label></li>';
            }
            return '';
          }, [ 'none', 'other' ] ) );
        $output .= '
        </ul>';
          if( array_key_exists( 'other', $defn ) && $defn['other'] ) {
            $output .= '
        <div class="fs-logic" data-visible ="[&quot;checked&quot;,&quot;'.
            $code.$extra.'&quot;,&quot;other&quot;]">
          <input type="text" value="" id="'.
            $code.'_other_text" name="'.
            $code.'_other_text" placeholder="Other please specify" />
        </div><!-- logic -->';
          }
        break;
        case 'textarea':
          $output .= '
        <label class="fs-textarea">'.$input.'
          <textarea id="'.$code.'" name="'.$code.'"'.
          ( array_key_exists( 'max', $defn ) && $defn['max'] ? ' maxlength="'.$defn['max'].'"' : '' ).
          '></textarea>
        </label>';
          break;
        default:        
          $output .= '
        <label class="fs-'.$defn['type'].'">'.$input.'
          <span><input type="'.$defn['type'].'" id="'.$code.'" name="'.$code.'" '.
          ( array_key_exists('required',$defn) && $defn['required'] ? 'required="required" ' : '').
          '/></span>
        </label>';
        ;
      }
      //$output .= '</label>';
    }
    if( array_key_exists( 'notes', $defn ) ) {
      $output .= '
        <div class="fs-notes">
          '.$this->render_intro( $defn['notes'] ).'
        </div><!-- notes -->';
    }
    return $output .'
      </div><!-- question -->
';
  }

  function get_page_class( $defn ) {
    $class = '';
    if( array_key_exists( 'logic', $defn ) ) {
      $class .= ' fs-logic';
    }
    if( array_key_exists( 'flag', $defn ) ) {
      $class .= ' fs-pageflag-'.$defn['flag'];
    }
    return $class;
  }
  
  function next_code() {
    return $this->sequence++;
  }
  
  function render_logic( $defn ) {
    if( ! array_key_exists( 'logic', $defn ) || ! $defn['logic'] ) {
      return '';
    }
    $logic = $defn['logic'];
    if( $logic ) {
      return implode( '', array_map( function($k) use ($logic) {
        return ' data-'.$k.'="'.HTMLentities(json_encode($logic[$k])).'"';
      }, array_keys( $logic ) ) );
    }
  }

  function render_intro( $str ) {
    return do_shortcode( preg_replace( [
      '/s{(?:\A|\n)\s*\b([^<].*?)\s*(?:\Z|\n)/mxs',
      '/^/mxs',
      '/^\s*/',
      '/\s*$/',
    ], [
      '\n<p>\n  $1\n<\/p>\n',
      '          ',
      '',
      ''
    ], $str ) );
  }
  
  function countries() {
    return array_map( 'html_entity_decode', [
      'Afghanistan',                                 '&Aring;land Islands',
      'Albania',                                     'Algeria',
      'American Samoa',                              'Andorra',
      'Angola',                                      'Anguilla',
      'Antarctica',                                  'Antigua and Barbuda',
      'Argentina',                                   'Armenia',
      'Aruba',                                       'Australia',
      'Austria',                                     'Azerbaijan',
      'Bahamas',                                     'Bahrain',
      'Bangladesh',                                  'Barbados',
      'Belarus',                                     'Belgium',
      'Belize',                                      'Benin',
      'Bermuda',                                     'Bhutan',
      'Bolivia, Plurinational State of',             'Bosnia and Herzegovina',
      'Botswana',                                    'Bouvet Island',
      'Brazil',                                      'British Indian Ocean Territory',
      'Brunei Darussalam',                           'Bulgaria',
      'Burkina Faso',                                'Burundi',
      'Cambodia',                                    'Cameroon',
      'Canada',                                      'Cape Verde',
      'Cayman Islands',                              'Central African Republic',
      'Chad',                                        'Chile',
      'China',                                       'Christmas Island',
      'Cocos (Keeling) Islands',                     'Colombia',
      'Comoros',                                     'Congo',
      'Congo, the Democratic Republic of the',       'Cook Islands',
      'Costa Rica',                                  'C&ocirc;te d&#39;Ivoire',
      'Croatia',                                     'Cuba',
      'Cyprus',                                      'Czech Republic',
      'Denmark',                                     'Djibouti',
      'Dominica',                                    'Dominican Republic',
      'Ecuador',                                     'Egypt',
      'El Salvador',                                 'Equatorial Guinea',
      'Eritrea',                                     'Estonia',
      'Ethiopia',                                    'Falkland Islands (Malvinas)',
      'Faroe Islands',                               'Fiji',
      'Finland',                                     'France',
      'French Guiana',                               'French Polynesia',
      'French Southern Territories',                 'Gabon',
      'Gambia',                                      'Georgia',
      'Germany',                                     'Ghana',
      'Gibraltar',                                   'Greece',
      'Greenland',                                   'Grenada',
      'Guadeloupe',                                  'Guam',
      'Guatemala',                                   'Guernsey',
      'Guinea',                                      'Guinea-Bissau',
      'Guyana',                                      'Haiti',
      'Heard Island and McDonald Islands',           'Holy See (Vatican City State)',
      'Honduras',                                    'Hong Kong',
      'Hungary',                                     'Iceland',
      'India',                                       'Indonesia',
      'Iran, Islamic Republic of',                   'Iraq',
      'Ireland',                                     'Isle of Man',
      'Israel',                                      'Italy',
      'Jamaica',                                     'Japan',
      'Jersey',                                      'Jordan',
      'Kazakhstan',                                  'Kenya',
      'Kiribati',                                    'Korea, Democratic People&#39;s Republic of',
      'Korea, Republic of',                          'Kuwait',
      'Kyrgyzstan',                                  'Lao People&#39;s Democratic Republic',
      'Latvia',                                      'Lebanon',
      'Lesotho',                                     'Liberia',
      'Libyan Arab Jamahiriya',                      'Liechtenstein',
      'Lithuania',                                   'Luxembourg',
      'Macao',                                       'Macedonia, the former Yugoslav Republic of',
      'Madagascar',                                  'Malawi',
      'Malaysia',                                    'Maldives',
      'Mali',                                        'Malta',
      'Marshall Islands',                            'Martinique',
      'Mauritania',                                  'Mauritius',
      'Mayotte',                                     'Mexico',
      'Micronesia, Federated States of',             'Moldova, Republic of',
      'Monaco',                                      'Mongolia',
      'Montenegro',                                  'Montserrat',
      'Morocco',                                     'Mozambique',
      'Myanmar',                                     'Namibia',
      'Nauru',                                       'Nepal',
      'Netherlands',                                 'Netherlands Antilles',
      'New Caledonia',                               'New Zealand',
      'Nicaragua',                                   'Niger',
      'Nigeria',                                     'Niue',
      'Norfolk Island',                              'Northern Mariana Islands',
      'Norway',                                      'Oman',
      'Pakistan',                                    'Palau',
      'Palestinian Territory, Occupied',             'Panama',
      'Papua New Guinea',                            'Paraguay',
      'Peru',                                        'Philippines',
      'Pitcairn',                                    'Poland',
      'Portugal',                                    'Puerto Rico',
      'Qatar',                                       'R&eacute;union',
      'Romania',                                     'Russian Federation',
      'Rwanda',                                      'Saint Barth&eacute;lemy',
      'Saint Helena',                                'Saint Kitts and Nevis',
      'Saint Lucia',                                 'Saint Martin (French part)',
      'Saint Pierre and Miquelon',                   'Saint Vincent and the Grenadines',
      'Samoa',                                       'San Marino',
      'Sao Tome and Principe',                       'Saudi Arabia',
      'Senegal',                                     'Serbia',
      'Seychelles',                                  'Sierra Leone',
      'Singapore',                                   'Slovakia',
      'Slovenia',                                    'Solomon Islands',
      'Somalia',                                     'South Africa',
      'South Georgia and the South Sandwich Islands','Spain',
      'Sri Lanka',                                   'Sudan',
      'Suriname',                                    'Svalbard and Jan Mayen',
      'Swaziland',                                   'Sweden',
      'Switzerland',                                 'Syrian Arab Republic',
      'Taiwan, Province of China',                   'Tajikistan',
      'Tanzania, United Republic of',                'Thailand',
      'Timor-Leste',                                 'Togo',
      'Tokelau',                                     'Tonga',
      'Trinidad and Tobago',                         'Tunisia',
      'Turkey',                                      'Turkmenistan',
      'Turks and Caicos Islands',                    'Tuvalu',
      'Uganda',                                      'Ukraine',
      'United Arab Emirates',                        'United Kingdom',
      'United States',                               'United States Minor Outlying Islands',
      'Uruguay',                                     'Uzbekistan',
      'Vanuatu',                                     'Venezuela, Bolivarian Republic of',
      'Viet Nam',                                    'Virgin Islands, British',
      'Virgin Islands, U.S.',                        'Wallis and Futuna',
      'Western Sahara',                              'Yemen',
      'Zambia',                                      'Zimbabwe',
    ] );
  }
  function register_short_code(){ 
    add_shortcode( 'form-submitter', array( $this, 'form_shortcode' ) );
  }
  function form_shortcode( $atts ) {
    return $this->set_code( $atts[0] )->fetch()->render();
  }
}
