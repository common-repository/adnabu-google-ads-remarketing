var AdNabuselectizeControl;

window.addEventListener('load', function() {
    do_selectize();
})

function enable_tracker(id, url, nonce) {
    window.open(url);
    window.addEventListener('focus',function () {
        jQuery.post('#', {enable_tracker:id, wp_nonce:nonce});
        location.reload();
    })
}

function toggle(id,nonce) {
    jQuery.post('#', {toggle:id, wp_nonce: nonce});
    location.reload();
}



function toggle_display_div(id) {
    var div = document.getElementById(id);
    if(div.style.display == "none"){
        div.style.display = ""
    }else{div.style.display= "none"}
}


var optionsList = [
    {text: '{Woocommerce Product ID}'},
    {text: '{Site Country}'},
    {text: '{Variation ID}'},
    {text: '{SKU}'},
    {text: '{GTIN}'}
];

function updateItemIDs(){
    var IDs = [];
    jQuery("#products").find("tr").each(function(){ IDs.push(this.id); });
    IDs = IDs.filter(Number);
    if(document.getElementById("input-tags") != null){
        var currentExpr = document.getElementById("input-tags").value ;
    }
    else{
        return;
    }


    jQuery.ajax({
        type:'POST',
        url:ajaxurl,
        dataType: "json",
        data: { action: 'get_merchant_centre_id',
            get_item_id : IDs,
            currentExpr : currentExpr
        },
        success:function(data) {
            if(data) {
                for(key in data){
                    jQuery('#' + key + ' .predicted_item_id').html(data[key])
                }
            }
        }
    });
};


function startEditMode() {
    jQuery('#editButton').hide();
    jQuery('#saveItemIDExpression').show();
    AdNabuselectizeControl.enable();
    jQuery('#predicted_item_id_div').show();
}


function do_selectize() {
    if(jQuery('#input-tags') == null){
        return;
    }
    updateItemIDs();
    var select = jQuery('#input-tags').selectize({
        persist: true,
        delimiter: ',',
        duplicates: true,
        valueField: 'text',
        labelField: 'name',
        searchField: ['name', 'text'],
        options: optionsList,
        render: {
            item: function(item, escape) {
                return '<div>' +
                    // (item.name ? '<span class="name">' + escape(item.name) + '</span>' : '') +
                    (item.text ? '<span class="text">' + escape(item.text) + '</span>' : '') +
                    '</div>';
            },
            option: function(item, escape) {
                var label = item.name || item.text;
                var caption = item.name ? item.text : null;
                return '<div>' +
                    '<span class="label">' + escape(label) + '</span>' +
                    // (caption ? '<span class="caption">' + escape(caption) + '</span>' : '') +
                    '</div>';
            },
        },
        create: true,
        onType: function() {
            jQuery('#predicted_item_id_div').show();
        },
        onDropdownOpen: function() {
            jQuery('#predicted_item_id_div').show();
        },
        onDropdownClose: function() {
            jQuery('#predicted_item_id_div').hide();
        },
        onItemAdd: function(item){
            if(item == '{GTIN}'){
                jQuery('#gtin_form_div').show();
            }
        },

        onItemRemove: function (item) {
            var isOption = false;
           for(i = 0; i< optionsList.length; i++){
               if(item == optionsList[i].text){
                   isOption = true;
                   if(item == "GTIN"){
                       jQuery('#gtin_form_div').hide();
                   }
               }
           }
           if(!isOption){
               var l = item.length * 20;
               jQuery('.selectize-input input').width(l +'px');
               jQuery('.selectize-input input').val(item);
           }
        }
    });

    if(typeof(myVariable) != "undefined"){
        AdNabuselectizeControl = select[0].selectize;
        AdNabuselectizeControl.on('change', function() {
            updateItemIDs();
        });
        var itemIdElements = AdNabuItemIDVar.split(',');
        itemIdElements.forEach(function(element) {
            AdNabuselectizeControl.addOption({text:element});
            AdNabuselectizeControl.refreshOptions();
            AdNabuselectizeControl.addItem(element);
        });
    }
}