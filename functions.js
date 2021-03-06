var row = null;
var destFields = [];
var destEvents = [];

function createFieldRow()
{
    var id = $(".table-id").val();
    var index = id.substring(id.length - 1, id.length);
    var sourceEvent = '';
    var sourceEventElem = '';
    var destEvent = '';
    var destEventElem = '';
    var destField = '[' + $('#dest-field-select').val() + ']';
    
    if ($('#field-value').val() != '') {
        var sourceField = "'" + $('#field-value').val() + "'";
        var sourceFieldElem = "<input class='setDestFieldsValues' type='hidden' name='setDestFieldsValues[" + index + "][]' value='" + $('#field-value').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            var destEvent = '[' + $('#dest-event-select').val() + ']';
            var destEventElem = "<input class='setDestEvents' type='hidden' name='setDestEvents[" + index + "][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='setDestFields' type='hidden' name='setDestFields[" + index + "][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillFieldForm(this)'
    }
    else {
        if ($('#event-select').val() && $('#event-select').val() != '') {
            var sourceEvent = '[' + $('#event-select').val() + ']';
            var sourceEventElem =  "<input class='pipingSourceEvents' type='hidden' name='pipingSourceEvents[" + index + "][]' value='" + $('#event-select').val() + "'/>";
        }
        var sourceField = '[' + $('#field-select').val() + ']';
        var sourceFieldElem = "<input class='pipingSourceFields' type='hidden' name='pipingSourceFields[" + index + "][]' value='" + $('#field-select').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            var destEvent = '[' + $('#dest-event-select').val() + ']';
            var destEventElem = "<input class='pipingDestEvents' type='hidden' name='pipingDestEvents[" + index + "][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='pipingDestFields' type='hidden' name='pipingDestFields[" + index + "][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillPipingFieldForm(this)'
    }

    var html = "<tr class='trigger-field-row'>" +
                    "<td>" + sourceEvent + sourceField + sourceEventElem + sourceFieldElem + "</td>" +
                    "<td>" + destEvent + destField + destEventElem + destFieldElem + "</td>" +
                    "</td><td><span class='fa fa-pencil-alt' onclick='" + editFunction + "'></span></td>" +
                    "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>" + 
                "</tr>";

    return html;
}

function createInstrRow()
{
    var id = $(".table-id").val();
    var index = id.substring(id.length - 1, id.length);
    var sourceEvent = '';
    var sourceEventElem = '';

    if ($('#instr-event-select').val() && $('#instr-event-select').val() != '') {
        var sourceEvent = '[' + $('#instr-event-select').val() + ']';
        var sourceEventElem = "<input class='sourceInstrEvents' type='hidden' name='sourceInstrEvents[" + index + "][]' value='" + $('#instr-event-select').val() + "'/>";
    }
    var sourceInstr = '[' + $('#instr-select').val() + ']';
    var sourceInstrElem = "<input class='sourceInstr' type='hidden' name='sourceInstr[" + index + "][]' value='" + $('#instr-select').val() + "'/>";

    var html = "<tr class='trigger-field-row'>" +
                    "<td>" + sourceEvent + sourceInstr + sourceEventElem + sourceInstrElem + "</td>" +
                    "<td>" + sourceEvent + sourceInstr + "</td>" +
                    "</td><td><span class='fa fa-pencil-alt' onclick='fillInstrForm(this)'></span></td>" +
                    "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>" + 
                "</tr>"

    return html;
}

function updateTable(elem)
{
    if ($(elem).attr("id") == "add-field-btn" && validateFieldForm())
    {
        var newRow = createFieldRow();
        clearFieldForm();
    }   
    else if (validateInstrumentForm())
    {
        var newRow = createInstrRow();
        clearInstrForm();
    }

    if (newRow)
    {
        if ($(elem).text() == 'Update')
        {
            row.after(newRow)
            row.remove();
            $(elem).text("Add");
        }
        else
        {
            var id = $(".table-id").val();
            $("#" + id).find("tbody").append(newRow);
        }
    }
    else
    {
        alert("Please make sure all fields are filled out before, clicking 'Add'!");
    }
}

function addTrigger()
{
    var triggers = $(".trigger-and-data-wrapper");
    var trigNum = triggers.length;
    
    var html = "<div class='form-group trigger-and-data-wrapper new-wrapper'>" +
                "<div class='det-trigger'>" +
                    "<div class='row'>" + 
                        "<div class='col-sm-2'>" +
                            "<h6>Trigger:</h6>" +
                        "</div>" +
                        "<div class='col-sm-9'></div>" +
                        "<div class='col-sm-1' style='text-align: center;'>" +
                            "<span class='fa fa-trash-alt delete-trigger-btn'></span>" +
                        "</div>" +
                    "</div>" +
                    "<input name='triggers[]' type='text' class='form-control det-trigger-input' required>" +
                "</div>" +
                "<p>" +
                    "Copy the following instruments/fields from source project to linked project when the above condition is true:" + 
                "</p>" +
                "<button type='button' data-toggle='modal' data-target='#add-field-modal' class='btn btn-primary btn-xs add-field-btn'>Add Field</button> " + 
                "<button type='button' data-toggle='modal' data-target='#add-instr-modal' class='btn btn-primary btn-xs add-instr-btn'>Add Instrument</button>" +
                "<br/><br/>" + 
                "<table class='table' id='table-" + trigNum + "'>" +
                    "<thead>" + 
                        "<tr>" + 
                            "<th>From Source Project</th>" +
                            "<th>To Linked Project</th>" +
                            "<th>Edit?</th>" +
                            "<th>Delete?</th>" +
                        "</tr>" + 
                    "</thead>" +
                    "<tbody>" +
                    "</tbody>" +
                "</table>" + 
            "</div>";
    
    if (triggers.length == 0)
    {
        $("#trigger-instr").after(html);
    }
    else if (triggers.length < 10)
    {
        triggers.last().after(html);
    }
    else
    {
        alert("You have reached the maximum number of allowed triggers (10)")
    }
}

function clearFieldForm()
{
    $('#event-select').val("");
    $('#field-value').val("");
    $('#field-select').val("");
    $('#dest-event-select').val("");
    $('#dest-field-select').val("");
}

function clearInstrForm()
{
    $('#instr-event-select').val("");
    $('#instr-select').val("");
}

function fillPipingFieldForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    $('#source-input').hide();
    $('#source-select').show();

    $('#field-select').val(row.find(".pipingSourceFields").val());
    $('#dest-field-select').val(row.find(".pipingDestFields").val());
    
    if (row.find(".pipingSourceEvents"))
    {
        $('#event-select').val(row.find(".pipingSourceEvents").val());
    }

    if (row.find(".pipingDestEvents"))
    {
        $('#dest-event-select').val(row.find(".pipingDestEvents").val());
    }
    
    $('#add-field-btn').text("Update");
    $('#add-field-modal').modal('show');
}

function fillFieldForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    $('#source-input').show();
    $('#source-select').hide();

    $('#field-value').val(row.find(".setDestFieldsValues").val());
    $('#dest-field-select').val(row.find(".setDestFields").val());

    if (row.find(".setDestEvents"))
    {
        $('#dest-event-select').val(row.find(".setDestEvents").val());
    }
    
    $('#add-field-btn').text("Update");
    $('#add-field-modal').modal('show');
}

function fillInstrForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    
    $('#instr-select').val(row.find(".sourceInstr").val());

    if (row.find(".sourceInstrEvents"))
    {
        $('#instr-event-select').val(row.find(".sourceInstrEvents").val()); 
    }

    $('#add-instr-btn').text("Update");
    $('#add-instr-modal').modal('show');
}

function validateFieldForm()
{
    if ($('#dest-field-select').val() == '' || 
        ($('#dest-event-select').is(':visible') && $('#dest-event-select').val() == '') ||
        ($('#field-value').is(':visible') && $('#field-value').val() == '') ||
        ($('#event-select').is(':visible') && $('#event-select').val() == '') ||
        ($('#field-select').is(':visible') && $('#field-select').val() == ''))
    {
        return false;
    }
    return true;
}

function validateInstrumentForm()
{
    if (($('#instr-event-select') && $('#instr-event-select').val() == '') || $('#instr-select').val() == '')
    {
        return false;
    }
    return true;
}

function updateAutocompleteItems(data)
{
    var metadata = JSON.parse(data);
    destFields = metadata.fields;
    destEvents = metadata.events;
    var isLongitudinal = metadata.isLongitudinal;

    if (isLongitudinal) {
        $(".dest-events-autocomplete").autocomplete({source: destEvents});
        $(".dest-events-autocomplete").prop("required", true);
        $(".dest-event-wrapper").show();
    }
    else {
        $(".dest-events-autocomplete").val("");
        $(".dest-events-autocomplete").prop("required", false);
        $(".dest-event-wrapper").hide();
    }
    $(".dest-fields-autocomplete").autocomplete({source: destFields});
}

function addError(id, error)
{
    $('#' + id).addClass("error");
    $('#' + id).after("<p class='error-msg'><i>" + error + "</i></p>");
}

function addTableErrors(errors, inputName)
{
    for(var index in errors)
    {
        var items = $("td > input[name='" + inputName + "[" + index + "][]']");
        console.log(items);
        for(var i in errors[index])
        {
            var msg =  errors[index][i];
            $(items[i]).after("<p class='error-msg'><i>" + msg + "</i></p>");
        }   
    }
}