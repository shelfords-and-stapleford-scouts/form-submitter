/*
+----------------------------------------------------------------------
| Copyright (c) 2015 Genome Research Ltd.
| This file is part of the Pagesmith web framework.
+----------------------------------------------------------------------
| The Pagesmith web framework is free software: you can redistribute
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

# Support functions predominantly for the header and footer of the new
# Sanger website
#
# Author         : js5
# Maintainer     : js5
# Created        : 2015-08-12
# Last commit by : $Author: js5 $
# Last modified  : $Date: 2018-07-30 14:16:48 +0100 (Mon, 30 Jul 2018) $
# Revision       : $Revision: 324 $
# Repository URL : $HeadURL: svn+psssh://surveys-genomethics-org@web-wwwsvn/repos/svn/sites/surveys-genomethics-org/trunk/htdocs/ge-survey/js/survey.js $
*/

(function($){
  'use strict';
  var keyup_timer, backup_timer,form_code,session;

  function load_in_response() { // We will need to do this with AJAX in the survey...
    //$.get( '/wp-content/plugins/form-submitter/form.php', [ 'code': code ] );
    // If exists cookie... we get the response back from the database....
    $.get( '/wp-content/plugins/form-submitter/form.php', { 'code': form_code })
      .always(function( data ) { console.log("A");
        if( data ) {
          $.each(data,function(nm,vls){
            var ex = Array.isArray(vls) ? '[]' : '',
                e  = $('input[name="'+nm+ex+'"]'),
                h;
            if(!e.length) {
              e = $('select[name="'+nm+ex+'"]');
              if( ! e.length ) {
                return;
              }
              e.eq(0).val( vls[0] );
            }
            if( e.eq(0).attr('type') === 'radio' ||
                e.eq(0).attr('type') === 'checkbox' ) {
              h = {};
              if(Array.isArray(vls)) {
                $.each(vls,function(i,v){
                  h[v]=1;
                });
              } else {
                h[vls]=1;
              }
              e.each(function(){
                if( h[ $(this).val() ] ) {
                  $(this).prop('checked', 'checked');
                  $(this).closest('label').addClass('fs-checked');
                }
              });
            } else {
              e.val( vls );
            }
          });
        }
        add_buttons();
        activate_question();
        add_nav();                      // Sets up navigation menu + embeds videos
        add_next_button();              // Adds functionality to the "next" button
        add_prev_button();              // Adds functionality to the "previous" button
        add_navigation_links();         // Add nav fn.
        add_on_change_methods();        // Adds functionality to form element changes to
                                  // check valid status & enable/disable buttons...
                                  // and to send "backup" post of responses...
        validate();                     // Validate form - this sets the "valid/invalid"
                                  // "complete/incomplete" flags on

        return;
      } );
    return;
    /* To copy results of "backed-up" responses */
  }

  function activate_question() {
    var v = $('input[name="fs_q"]').val(),          // Get value of page id
       $n = v ? $('#'+v) : $('.fs-page').eq(0);      // Either use this OR first page
    //    Page and section
    _act( $n, $n.closest('section') );
  }

  function _act_q( $n ) {
    if( $n.attr('id') ) {
      $('input[name="fs_q"]').val( $n.attr('id') );
    }
    $('ul.fs-checkbox:visible, ul.fs-radio:visible').each(function () { flow_elements($(this)); });
  }


  function add_nav() {
    $('section').each(function(){
      var n = $(this),
              id = n.attr('id');
      var x = $('<li />').html($(this).find('h2').eq(0).html()); // Add navigation entry
      x.attr('id','n_'+id);
      if( n.hasClass('fs-active') ) {
        x.addClass('fs-active');
      }
      $('#secnav').append(x);
    });
    $('#secnav li').last().addClass('fs-last');
  }

  function add_next_button() { // Adding functionality to next
    $('body').on('click','.fs-next',function() {
      var $n = $('div.fs-page').filter('.fs-active'),
          $p = $n.closest('section');
      if( $(this).hasClass('fs-disabled') && ! $n.hasClass('fs-pageflag-error') ||
          $(this).hasClass('fs-pending') ) {
        return;
      }
      if( $n.hasClass('fs-incomplete') ) {
        return;
      }
      if($n.hasClass('fs-pageflag-confirm')) { // This is the confirm page ... so we need to submit it!
        console.log( "FINAL SUBMIT" );
        return;
      }

      if( $n.next('.fs-page').length ) {  // Can we go next in the same section...
        $n.removeClass('fs-active');      // deactive current question...
        $n = $n.next('.fs-page');         // Jump to next question
        $n.addClass('fs-active');         // activate next question...
        _act_q( $n );
      } else if($p.next('section').length) { // Else can we jump sections...
        _deact( $n, $p );

        $p = $p.next('section');            // Jump to next section
        $n = $p.find('.fs-page').eq(0);     //    and question
        _act( $n, $p );
      } else {
        $('.fs-next').addClass('fs-disabled');
      }
      _check( $n, $p ); // Check the state of the new entry to see if it we
                      // Need to disable next button!
      if($n.hasClass('fs-hidden')) {
        $('.fs-next').trigger('click');
      }
    });
  }

  function add_prev_button() { // Adding functionality to prev..
    $('body').on('click','.fs-prev',function(){
      if( $(this).hasClass('fs-pending') ||
          $(this).hasClass('fs-disabled') ) {
        return;
      }
      var $n = $('div.fs-page').filter('.fs-active'),
          $p = $n.closest('section');

      if( $n.prev('.fs-page').length ) { // Can we go previous in the same section...
        $n.removeClass('fs-active');  // deactive current question...
        $n = $n.prev('.fs-page'); // Jump to previous question
        $n.addClass('fs-active');     // activate previous question...
        _act_q( $n );
      } else if($p.prev('section').length) { // Else can we jump sections...
        _deact( $n, $p );
        $p = $p.prev('section');            // Jump to previous section
        $n = $p.find('.fs-page').last();   //    and question
        _act( $n, $p );
      } else {
        $('.fs-prev').addClass('fs-disabled');
      }
      _check( $n, $p ); // Check the state of the new entry to see if it we
                      // Need to disable next button!
      if( $n.hasClass('fs-hidden') ) {
        $('.fs-prev').trigger('click');
      }
    });
  }

  function add_navigation_links() {
    $('#secnav').on('click','li.fs-complete',function(){
      var $n = $('div.fs-page').filter('.fs-active'),
          $p = $n.closest('section'),
          $p_new = $( '#'+$(this).attr('id').substr(2) ),
          $n_new = $p_new.find('.fs-page').eq(0);
      _deact( $n, $p );
      _act( $n_new, $p_new );
      _check( $n_new, $p_new );
      if( $n_new.hasClass('fs-hidden') ) {
        $('.fs-next').trigger('click');
      }
    });
  }

  // On change/key up - put a 5 second delay timer call to write the changes back to the db...
  // If another change is made in that time - the first call is ignored...
  function to_do_on_change(nd){
    if( keyup_timer ) {
      window.clearTimeout(keyup_timer);
    }
    if( backup_timer ) {
      window.clearTimeout(backup_timer);
    }
    validate();
    backup_timer = window.setTimeout( function() {
      $.post(
        $('#submitform').attr('action'),
        $('#submitform').serialize(),
        function( $data ) {
          session = $data['code'];
          $('input[name="session_code"]').val( session );
          console.log( session );
        }
      );
    },2000);
    // Check status of other inputs for this name - if radio button
    // We need to clear the other labels - this means that we highlight labels
    $('input[name="'+$(nd).attr('name')+'"]').each(function(){
      if( $(this).prop('checked') ) {
        $(this).closest('label').addClass('fs-checked');
      } else {
        $(this).closest('label').removeClass('fs-checked');
      }
    });
  }

  function add_on_change_methods() {
    var skip_before_unload = false;
    if( ! $('.fs-form').length ) {
      skip_before_unload = true;
    }
    $('.no-on-unload').on('click',function(){
      skip_before_unload = true;
    });
    window.onbeforeunload = function() {
      if( skip_before_unload ) {
        return;
      }
      if( backup_timer ) {
        $.post( $('#submitform').attr('action'), $('#submitform').serialize() );
        window.clearTimeout(backup_timer);
      }
      return 'Incomplete'; // 'You have not completed the survey yet.';
    };

    $('#submitform')
      .on('change','select,input',function(){
        return to_do_on_change(this);
      })
      .on('keyup','input',function(){
        if( keyup_timer ) {
          window.clearTimeout(keyup_timer);
        }
        keyup_timer = window.setTimeout( function() {
          return to_do_on_change(this);
        },150 );
      })
      .on('submit',function(){
        if( backup_timer ) { // Ignore the backup request as we are doing submit...
          window.clearTimeout(backup_timer);
        }
        if( keyup_timer ) {
          window.clearTimeout(keyup_timer);
        }
        validate();
        if( $('section.fs-incomplete').length ) {
          return false;
        }
        window.onbeforeunload = null;
        $('#submitform').find(':input').attr('readonly','readonly'); // Make form readonly ....!
        $.post(
          $('#submitform').attr('action'),
          $('#submitform').serialize()+'&__action=confirm'
        );
        return true;
      });
  }

/* Form validation .... */
  function validate( ) {
    apply_logic();      // First apply logic to work out which divs should/shouldn't be shown!
    $('.grid_layout:visible ul').each(function () { flow_elements($(this)); });
    check_questions();  // Second we loop through each question in turn to see if they are complete & valid
    check_sections();   // Third loop through sections ...
    check();            // Sort out state of prev/next buttons!
  }

  function get_check( name ) {
    return $('input[name="'+name+'"]:checked').map(function(){return $(this).val();}).get();
  }
  function check_questions() {
    $('div.fs-page').each(function(){      // Check to see which questions are valid!
      var $q = $(this), valid = 1, complete = 1;

      $q.removeClass('fs-valid').removeClass('fs-complete')             // Remove status flags on questions!
        .removeClass('fs-invalid').removeClass('fs-incomplete');

      if( $q.hasClass('fs-information') || $q.hasClass('fs-hidden') ) { // These questions are always valid & complete;
        $q.addClass('fs-valid').addClass('fs-complete');
        return;
      }
  // CURRENT ASSUMPTIONS
  //  ** may need optional flag on some fields
      // All radio button sets require an answer if NOT hidden
      $.each($(this).find('select'),function(x,v) {
        if( $(v).val().match(/^==/) ) {
          complete = 0;
          return false;
        }
      });
      $.each(_group( $(this).find('input[type="radio"]') ),function(x,v) {
        if( v.length === 0 ) {
          complete = 0;
          return false;
        }
      });
      // All checkbox sets require at least one answer if NOT hidden
      // and are invalid IF there are 2 or more selected AND one of the selected is tagged as unique!
      $.each(_group( $(this).find('input[type="checkbox"]') ),function(x,v) {
        if( v.length === 0 ) {
          complete = 0;
          return false;
        }
        $.each(v,function(i,x){
          if($(x).data()) {
            if( $(x).data('min') && v.length < $(x).data('min') ) {
              complete = 0;
              return false;
            }
            if( $(x).data('max') && v.length > $(x).data('max') ) {
              valid = 0;
              complete = 0;
              return false;
            }
          }
        });
        if( v.length === 1 ) {
          return;
        }
        $.each(v,function(i,x){ // Deal when we have more than one value - check to see if any tagged as "unique"
          if( $(x).data('unique') ) {
            valid = 0;
            complete = 0;
            return false;
          }
        });
      });

      // All string inputs are required if NOT hidden...
      $(this).find('input[type="text"]').each(function(){
        if( $(this).hasClass('fs-optional') || $(this).closest('.fs-hidden').length ) {
          return;
        }
        if($.trim($(this).val()) === '' ) {
          complete = 0;
          return false;
        }
      });

      // Add appropriate classes
      $q.addClass( valid    ? 'fs-valid'    : 'fs-invalid' )
        .addClass( complete ? 'fs-complete' : 'fs-incomplete' );
    });
  }

  function check_sections() {
    var incomplete = 0; // Set flag to incomplete on first section that is incomplete!
    $('section').each(function(){
      var $s = $(this), $n = $('#n_' + $s.attr('id'));
      if( $s.find('div.fs-incomplete').length ) {
        incomplete = 1;
      }
      if( incomplete ) {
        $s.addClass('fs-incomplete');
        $n.removeClass('fs-complete');
      } else {
        $s.removeClass('fs-incomplete');
        $n.addClass('fs-complete');
      }
    });
  }

/* Support methods... */

  function _deact( $n, $p ) {
    $n.removeClass('fs-active');  // deactivate current section...
    $p.removeClass('fs-active');
    $('#n_'+$p.attr('id')).removeClass( 'fs-active' );  // Remove nav active flag...
  }

  function _act( $n, $p ) {
    $n.addClass('fs-active');  // activate current section...
    $p.addClass('fs-active');
    $('#n_'+$p.attr('id')).addClass( 'fs-active' );  // Remove nav active flag...
    _act_q( $n );
  }

  function check() {
    var $n = $('div.fs-page').filter('.fs-active');
    return _check( $n, $n.closest('section') );
  }

  function _check( n, p ) {
    $('.fs-prev').removeClass('fs-disabled');
    $('#secnav').show();
    $('#langsel').hide();
    if( p.prev('section').length === 0 && n.prev('.fs-page').length === 0 ||
      n.hasClass('fs-pageflag-success')
    ) { // Check to see if we have another question before this!
      $('.fs-prev').addClass('fs-disabled');
      // We need to see if we have completed any answers here!!!
      if( $('input:checked').length === 0 ) {
        $('#secnav').hide();
        $('#langsel').show();
      }
    }
    $('.fs-next').removeClass('fs-disabled')
              .removeClass('fs-pending');
    $('.fs-next').html( n.hasClass('fs-pageflag-confirm') ? 'Confirm' : 'Next' );


    if( p.next('section').length === 0 &&
        n.next('.fs-page').length   === 0 ||
        n.hasClass('fs-pageflag-success') ||
        n.hasClass('fs-pageflag-error')
    ) { // Check to see if we have another question after this!
      $('.fs-next').addClass('fs-disabled');
    } else if( n.hasClass('fs-incomplete') ) {
      $('.fs-next').addClass('fs-pending');
    }
  }

  function _group( $nodes ) { // Group a series of inputs by their name...
    var gp = {};
    $nodes.each(function(){
      if( $(this).closest('.fs-hidden').length ||
          $(this).closest('.fs-optional').length ) { // Skip if hidden - we aren't interested!
        return;
      }
      var n = $(this).attr('name');
      if( ! (n in gp) ) {
        gp[n] = [];
      }
      if( $(this).prop('checked') ) {
        gp[n].push(this);
      }
    });
    return gp;
  }

  function add_progress_indicator() {
    $('.fs-page').prepend('<div class="fs-progress"></div>');
  }

  function add_buttons() {
    $('#submitform').append(
      '<div class="fs-buttons">'+
        '<span id="submit_prev"   class="fs-prev">Previous</span>'+
        '<span id="submit_next"   class="fs-next">Next</span>'+
      '</div>' );
  }
  /* Apply logic to questions/sub-questions
    Two sets of logic - visible + required
    Uses a "syntax" based on ldap queries..
    Simple logic: {input_field:'value'}
    More complex logic uses lDAPs { condition, logic, ... }
    Where condition is one of 'not', 'or', 'and'
  */
  function apply_logic() {
    $('.fs-logic').each(function(){
      var x = $(this).data();
      if(x && x.visible) {
        if( eval_logic( x.visible ) ) {
          $(this).removeClass('fs-hidden');
        } else {
          $(this).addClass('fs-hidden');
        }
      }
      if(x && x.required) {
        if( eval_logic( x.required ) ) {
          $(this).removeClass('fs-optional');
        } else {
          $(this).addClass('fs-optional');
        }
      }
    });
  }
  function eval_logic( x ) {
    var j, user_values, name;
    if( $.isArray( x ) ) {
      switch( x[0] ) {
        case 'not' :
          return ! eval_logic( x[1] );
        case 'or' :
          for( j = x.length; j>1; j ) {
            j--;
            if( eval_logic( x[j] ) ) {
              return true;
            }
          }
          return false;
        case 'and' :
          for( j = x.length; j>1; j ) {
            j--;
            if( ! eval_logic( x[j] ) ) {
              return false;
            }
          }
          return true;
        case 'checked' :
          return( $('input[name="'+x[1]+'"][value="'+x[2]+'"]:checked').length !== 0 );
        case '=' :
          return eval_string( x[1] ) == eval_string( x[2] );
      }
      return false;
    }
    return 0;
  }

  function eval_string( string ) {
    if( string.startsWith('_.') ) {
      var k = string.substr(2);
      var n = $('input[name="'+k+'"], select[name="'+k+'"]').val();
      return n;
    } else {
      return string;
    }
  }

  function get_code_from_form() {
    form_code = $('#submitform').find('input[name="code"]').val();
  }
// The actual script....
  get_code_from_form();
  load_in_response();             // Load in response code to go here
  //add_progress_indicator();

/* Not submit form related - but keep the buttons the same size and so wrap nicely... */
  function flow_elements(ul_element) {
    var sz = 5,
      $f = $(ul_element),
      t  = $f.find('li label, li > span').add($f.prev('span')),
      z  = [],
      tp = 0,
      h  = 0;
    t.each( function(i,n){
      $(n).height('auto').css({'padding-top':0,'padding-bottom':0}).parent().removeClass( 'clear' );
      var nt = $(n).position().top, h1 = $(n).height();
      if( nt === tp ) {
        z.push(n);
        if( h1 > h ) { h = h1; }
      } else {
        $.each(z, function(j,m) {
          var q = $(m).height();
          q = sz + ( h - q )/2 + 'px';
          $(m).css({ 'padding-top': q, 'padding-bottom': q });
        });
        $(z).last().parent().next().addClass('clear');
        nt = $(n).position().top;
        z = [n];
        h = h1;
        tp = nt;
      }
    });
    $.each(z, function(j,m) {
      var q = $(m).height();
      q = sz + ( h - q )/2 + 'px';
      $(m).css({ 'padding-top': q, 'padding-bottom': q });
    });
  }

  $(window).resize(function () {
    $('ul.fs-checkbox:visible, ul.fs-radio:visible').each(function () { flow_elements($(this)); });
  });

  var lastHeight = 0;
  function pollSize() {
    var newHeight = $(window).height();
    if (lastHeight === newHeight) {
      return;
    }
    lastHeight = newHeight;
    $(window).resize();
  }
  window.setInterval(pollSize, 250);
}(jQuery));
