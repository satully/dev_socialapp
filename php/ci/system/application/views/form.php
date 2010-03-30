<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"http://www/w3/org/TR/xhtml1/DTD/xhtml-transitional.dtd">
<html xmlns = 'http://www.w3.org/1999/xhtml" lang="ja" xml:lang='ja'>
<head>
    <meta http-equiv='Content-Type' content="text/html; charset=UTF-8"/>
    <link rel="stylesheet" href="<?=base_url();?>css/form.css" type="text/css" />
    <title>コンタクトフォーム</title>
</head>
<body>
<?=$this->load->view('form_header');?>
<div id='main'>
    <div class='title_banner'>
        <img src='<?=base_url();?>images/icons/form_title.jpg' alt='お問い合わせ' width='580' height='70' />
    </div>
    <div class="outer_frame">
        <?=form_open('form/confirm');?>
        <?=form_hidden('ticket',$this->ticket);?>
        <table>
        <tr>
            <th>
                名前
            </th>
            <td>
                <input type="text" name='name' value='<?=$this->validation->name;?>'size='30'/>
                <?=$this->validation->name_error;?>
            </td>
        </tr>
        