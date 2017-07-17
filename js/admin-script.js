jQuery(document).ready(function(){
    jQuery('#addcatfeed').click(function(){
        var cat = jQuery('#fbcat').val();
        var isdupe = fbfs_check_duplicate_catexclude(cat);
        var catname = jQuery('#fbcat option[value="'+cat+'"]').text();
        var catfeed = jQuery('#catfeed').val();
        if(!catfeed){                      
            jQuery('#catfeed').focus().css('border','1px solid red');
            return;
        } 
        if(catfeed.toLowerCase().indexOf('http://feeds.feedblitz.com/') != 0){
            alert('Please enter a valid feedblitz feed URL');
            jQuery('#catfeed').focus().select().css('border','1px solid red');
            return;
        }
        jQuery('#catfeed').removeAttr('style');
        var htmlinsert = '<tr style="display: none" id="cat-'+cat+'" class="'+cat+'"><td><input type="hidden" name="feedsmart_settings[catfeeds]['+cat+']" value="'+catfeed+'"/>'+
        catname + '</td><td>'+catfeed+'</td><td><span style="padding: 1px; margin-left: 5px; background-color: red; border: 1px solid black; cursor:pointer;" class="fbdelete">X</span></td></tr>';
        if(isdupe == 'current'){
            //replace existing
            jQuery('#cat-'+cat).replaceWith(htmlinsert);
            jQuery('#cat-'+cat).css('background-color','yellow').fadeOut(100).fadeIn(500).removeAttr('style');
        } else {
            if(isdupe == 'exclude'){
                alert('Already excluded ' + catname);
                return;
            } else {
                jQuery('#none').remove();
                jQuery('#current').append(htmlinsert);
                jQuery('#cat-'+cat).fadeIn();
                jQuery('#catfeed').val('');
            }
        } 
    });
    jQuery('#addcatexclude').click(function(){
        var catexclude = jQuery('#fbcatexclude').val();
        var isdupe = fbfs_check_duplicate_catexclude(catexclude);
        var catname = jQuery('#fbcatexclude option[value="'+catexclude+'"]').text();
        if(isdupe == 'exclude'){
            alert('Already excluded ' + catname);
            return;
        }
        if(isdupe == 'current'){
            alert('Cannot exlude exisiting redirect for '+catname);
            return;
        }
        var htmlinsert = '<tr style="display: none" id="catexclude-'+catexclude+'" class="'+catexclude+'"><td><input type="hidden" name="feedsmart_settings[catsexclude][]" value="'+catexclude+'"/>'+
        catname + '</td><td><span style="padding: 1px; margin-left: 5px; background-color: red; border: 1px solid black; cursor:pointer;" class="fbdelete">X</span></td></tr>';
        jQuery('#noneexclude').remove();
        jQuery('#currentexclude').append(htmlinsert);
        jQuery('#catexclude-'+catexclude).fadeIn();

    });
    jQuery('.fbdelete').live('click',function(){
        var row = jQuery(this).parents('tr');
        jQuery(row).fadeOut('slow',function(){jQuery(row).remove()});
    });
});

function fbfs_check_duplicate_cat(cat){
    var isdupe = false;
    jQuery('#current tr').each(function(){
        if(jQuery(this).hasClass(cat)){
            isdupe = true;
        }
    });
    return isdupe;
}

function fbfs_check_duplicate_catexclude(cat){
    var isdupe = false;
    jQuery('#currentexclude tr').each(function(){
        if(jQuery(this).hasClass(cat)){
            isdupe = 'exclude';
        }
    });
    jQuery('#current tr').each(function(){
        if(jQuery(this).hasClass(cat)){
            isdupe = 'current';
        }
    });
    return isdupe;
}