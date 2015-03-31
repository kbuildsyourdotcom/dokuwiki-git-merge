<?php
/**
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_door43gitmerge extends DokuWiki_Action_Plugin {

  function register(Doku_Event_Handler $controller) {
    $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_ajax_call');
    $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_tpl_act', array());
    $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'compile_merge_data', array());
    $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'render_merge_interface', array());
    $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'handle_add_merge_button');
  }

  function _ajax_call(&$event, $param) {
    global $INPUT;

    if($event->data!=='door43gitmerge') {
      return;
    }
    $event->stopPropagation();
    $event->preventDefault();

    switch($INPUT->get->str('do')) {
      case 'dismiss':
        $this->_dismiss($INPUT->get->str('device'), $INPUT->get->str('frame'));
        break;
      case 'apply':
        $this->_apply($INPUT->get->str('device'), $INPUT->get->str('frame'));
        break;
      case 'edit':
        $this->_apply_edit($INPUT->get->str('content'), $INPUT->get->str('frame'));
        break;
      default:
        break;
    }

  }

  function handle_tpl_act(&$event, $param) {
    global $INPUT;

    $do = $event->data;
    if(is_array($do)) list($do) = array_keys($do);

    switch($do) {
      case 'door43gitmerge':
        $this->show_merge_interface = true;
        $event->data = 'show';
        break;
      case 'door43gitmerge-dismiss':
        $this->_dismiss($INPUT->get->str('device'), $INPUT->get->str('frame'));
        $event->data = 'show';
        break;
      case 'door43gitmerge-apply':
        $this->_apply($INPUT->get->str('device'), $INPUT->get->str('frame'));
        $event->data = 'show';
        break;
      case 'door43gitmerge-edit':
        $this->_edit($INPUT->get->str('content'), $INPUT->get->str('frame'));
        $event->data = 'show';
        break;
      default:
        break;
    }
  }

  function _init() {
    global $ID;

    if ($this->ready) return;
    $this->ready = 1;
    list($this->lang, $this->proj, $this->id) = explode(':', $ID);
    $this->repo_path = $this->getConf('repo_path').'/';
    $this->page_path = 'uw-'.$this->proj.'-'.$this->lang.'/'.$this->id.'/';
    $this->devices_list_filename = $this->proj.'-'.$this->lang.'-'.$this->id.'.updated';
  }
  function _load_content() {
    global $ID;

    //load source
    $source = preg_replace(
      '/(?:[\r\n]*)({\{[^\}]*\}\})(?:[\r\n]*)/',
      '<!-- Frame -->$1<!-- Image -->',
      rawWiki($ID)
    );
    $source = trim($source.'');
    $source = preg_replace(
      '/(?:[\r\n]+)/',
      '<!-- Footer -->',
      $source
    );
    list($source, $footer) = explode('<!-- Footer -->', $source);
    $frames = explode('<!-- Frame -->', $source);

    //set data
    $this->header = array_shift($frames);
    $this->footer = $footer;
    unset($footer);
    $frame_keys = array();
    for ($i=1; $i<=count($frames); $i++) array_push($frame_keys, str_pad($i, 2, '0', STR_PAD_LEFT));
    unset($i);
    $this->content = array_combine($frame_keys, $frames);
    unset($frames, $frame_keys);
  }
  function _dismiss($device, $frame) {
    $this->_init();

    //remove frame from .updated
    $frame_list_file = $this->repo_path.$device.'/'.$this->page_path.'.updated';
    $frame_list = @file_get_contents($frame_list_file);
    $frames = explode("\n", preg_replace('/[\r\n]+/', "\n", $frame_list) );
    foreach($frames as $index=>$content) if ($content==$frame) unset($frames[$index]);
    unset($index, $content);
    sort($frames);
    $frame_list = implode("\n", $frames);
    file_put_contents($frame_list_file, $frame_list);
    unset($frame_list_file, $frame_list, $frames);

    //add frame to .dismissed
    $frame_list_file = $this->repo_path.$device.'/'.$this->page_path.'.dismissed';
    $frame_list = @file_get_contents($frame_list_file);
    $frames = explode("\n", preg_replace('/[\r\n]+/', "\n", $frame_list) );
    foreach($frames as $index=>$content) if ($content==$frame) unset($frames[$index]);
    unset($index, $content);
    array_push($frames, $frame);
    sort($frames);
    $frame_list = implode("\n", $frames);
    file_put_contents($frame_list_file, $frame_list);
    unset($frame_list_file, $frame_list, $frames);
  }
  function _apply($device, $frame) {
    $this->_init();
    $this->_load_content();

    //replace frame with new content
    $content = @file_get_contents($this->repo_path.$device.'/'.$this->page_path.$frame.'.txt');
    list($image) = explode('<!-- Image -->', $this->content[$frame]);
    $this->content[$frame] = $image.'<!-- Image -->'.$content;
    $source = str_replace('<!-- Image -->', "\n\n", $this->header."\n\n".implode("\n\n", $this->content)."\n\n".$this->footer);

    //save source
    //#TODO: add save trigger
  }
  function _edit($content, $frame) {
    $this->_init();
    $this->_load_content();

    //replace frame with new content
    list($image) = explode('<!-- Image -->', $this->content[$frame]);
    $this->content[$frame] = $image.'<!-- Image -->'.$content;
    $source = str_replace('<!-- Image -->', "\n\n", $this->header."\n\n".implode("\n\n", $this->content)."\n\n".$this->footer);

    //save source
    //#TODO: add save trigger
  }

  function compile_merge_data(&$event, $param) {
    global $ID, $INFO;

    $this->_init();
    $projects = array('obs');
    $devices_list = @file_get_contents($this->repo_path.$this->devices_list_filename);
    $this->devices = array_flip( explode('\n', preg_replace('/[\r\n]+/', '\n', $devices_list) ) );
    unset($devices_list);
    if( array_keys( array_slice($this->devices, 0, 1) )[0]=='' ) array_shift($this->devices);
    $this->device_count = count( $this->devices );
    $this->on = in_array($this->proj, $projects) && $this->id!='' && $this->id==preg_replace('/[^0-9]*/', '', $this->id);

    // compile available changes
    $this->frames = array();
    foreach ($this->devices as $device=>&$user) {
      $user = json_decode( @file_get_contents($this->repo_path.$device.'/profile/contact.json'), true );
      $frame_list = @file_get_contents($this->repo_path.$device.'/'.$this->page_path.'.updated');
      $frames = explode("\n", preg_replace('/[\r\n]+/', "\n", $frame_list) );
      unset($frame_list);
      foreach ($frames as $frame) {
        $this->frames[$frame][$device] = @file_get_contents($this->repo_path.$device.'/'.$this->page_path.$frame.'.txt');
      }
      unset($frames, $frame, $device);
    }
    @ksort($this->frames);
    if( array_slice($this->frames, 0, 1)=='' ) array_shift($this->frames);
    $this->frame_count = count($this->frames);

    if($event->data != 'show' || !$this->show_merge_interface) return; // nothing to do for us

    //$event->preventDefault();
  }

  function render_merge_interface(&$event, $param) {
    global $ID, $INFO, $INPUT;

    if($this->show_merge_interface) {

      $this->_load_content();
      echo p_render('xhtml', p_get_instructions($this->header), $info);

/** /
      //parse frames from raw page content and echo page header
      $raw_data = preg_replace(
        '/(?:[\r\n]*)({\{[^\}]*\}\})(?:[\r\n]*)/',
        '<!-- Frame -->$1<!-- Image -->',
        rawWiki($ID)
      );
      $frames = array_slice( explode('<!-- Frame -->', $raw_data), 0, -1 );
      echo p_render('xhtml',p_get_instructions(array_shift($frames)),$info);
      $frame_keys = array();
      for ($i=1; $i<=count($frames); $i++) array_push($frame_keys, str_pad($i, 2, '0', STR_PAD_LEFT));
      $frames = array_combine($frame_keys, $frames);
      unset($frame_keys);
/**/

      //loop through frames with available merge options
      foreach($this->frames as $frame=>$data_array) {
        //list($image, $current_content) = explode('<!-- Image -->', $frames[$frame]);
        list($image, $current_content) = explode('<!-- Image -->', $this->content[$frame]);
        echo p_render('xhtml',p_get_instructions($image),$info);
        echo p_render('xhtml',p_get_instructions($current_content),$info);
        echo $this->getLang('version_to_compare').': ';
        echo '<select class="door43gitmerge-diff-switcher" data-frame="'.$frame.'">';
        foreach ($data_array as $device=>$new_content) {
          if (!isset($first_device)) $first_device = $device;
          echo '<option value="'.$device.'">'.$this->devices[$device]['name'].'</option>';
        }
        echo '<option value="all">'.$this->getLang('show_all').'</option>';
        unset($device, $new_content);
        echo '</select>';
        echo '<div id="frame-'.$frame.'" class="frame-diffs">';
        foreach ($data_array as $device=>$new_content) {
          $this->html_diff($frame, $device, $current_content, $new_content, $device==$first_device);
        }
        unset($device, $new_content, $first_device);
        echo '</div>';
      }
      echo p_render('xhtml', p_get_instructions($this->footer), $info);
?>
<script type="text/javascript">/*<![CDATA[*/
jQuery(document).on('change input', '.door43gitmerge-diff-switcher', function(){
  var elem = jQuery(this)
    , lastDevice = elem.attr('data-last-value')
    , device = elem.val()
    , frame = elem.attr('data-frame')
    , diffs = jQuery('#frame-'+frame+'>.table');
  if (lastDevice==device) return;
  lastDevice = device;
  if (device=='all') diffs.addClass('show');
  else diffs.removeClass('show').filter('[data-device="'+device+'"]').addClass('show');
});
/*!]]>*/</script>
<?php
      $event->preventDefault();
    }
  }

  public function handle_add_merge_button(&$event, $param) {
    global $ID, $REV, $INFO;

    if(!$this->on) return;

    $mergens = cleanID($this->getConf('mergens'));
    if($this->show_merge_interface) {
      echo '<li><a href="'.wl($INFO['id']).'" class="action show" accesskey="v" rel="nofollow" title="'.$this->getLang('back').' [V]"><span>'.$this->getLang('back').'</span></a></li>';
    } else {
      $badge = $this->frame_count>0 ? $this->frame_count : '';
      echo '<li data-badge="'.$badge.'"><a class="action merge" title="'.$this->getLang('merge').'" href="'.wl($INFO['id']).'?do='.$mergens.'"><span>'.$this->getLang('merge').'</span></a></li>';
    }
  }

  private function html_diff($frame, $device, $l_text = '', $r_text = '', $show = 1) {
    global $ID, $REV, $lang, $INPUT,$INFO;

    /*
     * Determine diff type
     */
    if($INFO['ismobile']) {
      $type = 'inline';
    } else {
      $type = 'sidebyside';
    }

    /*
     * Create diff object and the formatter
     */
    require_once(DOKU_INC.'inc/DifferenceEngine.php');
    $diff = new Diff(explode("\n", $l_text), explode("\n", $r_text));

    if($type == 'inline') {
      $diffformatter = new InlineDiffFormatter();
    } else {
      $diffformatter = new TableDiffFormatter();
    }

    if($show == 1) {
      $class = ' show';
    }

    /*
     * Display diff view table
     */
    ?>
    <div class="table frame-<?php echo $frame; ?>-diff<?php echo $class; ?>" data-frame="<?php echo $frame; ?>" data-device="<?php echo $device; ?>">
        <table class="diff diff_<?php echo $type ?>">

        <?php
        //navigation and header
        if($type == 'inline') { ?>
            <tr>
                <td class="diff-lineheader">-</td>
                <td>Current Version</td>
            </tr>
            <tr>
                <th class="diff-lineheader">+</th>
                <th><?php echo $this->devices[$device]['name']; ?>'s Version</th>
            </tr>
        <?php } else { ?>
            <tr>
                <th colspan="2">Current Version</th>
                <th colspan="2"><?php echo $this->devices[$device]['name']; ?>'s Version</th>
            </tr>
        <?php }

        //diff view
        echo html_insert_softbreaks($diffformatter->format($diff)); ?>

        </table>
        <form>
            <input type="hidden" name="frame" value="<?php echo $frame; ?>">
            <input type="hidden" name="device" value="<?php echo $device; ?>">
            <div class="door43gitmerge-actions">
                <input name="do[door43gitmerge-dismiss]" type="submit" class="door43gitmerge-dismiss" value="<?php echo $this->getLang('dismiss'); ?>">
                <input name="do[door43gitmerge-edit]" type="submit" class="door43gitmerge-edit" value="<?php echo $this->getLang('edit_and_apply'); ?>">
                <input name="do[door43gitmerge-apply]" type="submit" class="door43gitmerge-apply" value="<?php echo $this->getLang('apply'); ?>">
            </div>
        </form>
    </div>
<?php
  }
}

// vim:ts=4:sw=4:et: