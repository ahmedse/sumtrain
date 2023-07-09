function handleSave() {
    // get selections
    var selectElement = document.getElementById('id_institute');
    var selectedValue = selectElement.value;
    var selectedText = selectElement.options[selectElement.selectedIndex].text;

    var selectElement_session = document.getElementById('id_session');
    var selectedValue_session = selectElement_session.value;
    var selectedText_session = selectElement_session.options[selectElement_session.selectedIndex].text;

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Handle the response from the server
           let response = JSON.parse(this.responseText);
           console.log(response);           
           setStatus(response);

        }
    };
    xmlhttp.open("GET", "handle_student_selection.php?institute=" + selectedText +"&session=" + selectedValue_session, true);
    xmlhttp.send();
}

function setStatus(response) {
    var myDiv = document.getElementById("status");
    myDiv.innerHTML= "";
    myDiv.innerHTML  = response[1];
    if (response[0]> 0){                    
        myDiv.style.backgroundColor = "lightyellow";
    }
    else{            
        //myDiv.innerHTML = myDiv.innerHTML + " <br>" + response[1];
        myDiv.style.backgroundColor = "lightblue";
    }
  }

function handleCancel() {
    //alert("Cancel");
    window.location.href = "http://lms.local/course/view.php?id=177#section-0";
}

function handleSelectChange() {
    //alert("bbb");
    var selectElement = document.getElementById('id_institute');
    var selectedValue = selectElement.value;
    var selectedText = selectElement.options[selectElement.selectedIndex].text;
 
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Handle the response from the server
            let response = JSON.parse(this.responseText);
            console.log(response);     
      
            var selectElement_session = document.getElementById('id_session');
            // remove all options
            while (selectElement_session.options.length > 0) {
                selectElement_session.remove(0);
            }
            
            // populate sessions
            var options = response;
            
            for (var label in options) {
                var value = options[label];
                var option = new Option(value, label, disabled=false);

                console.log(option);
                selectElement_session.add(option);
            }
        }
    };
    xmlhttp.open("GET", "handle_select_change.php?value=" + selectedText, true);
    xmlhttp.send();
}

window.onload = function() {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Handle the response from the server
            let response = JSON.parse(this.responseText);
            console.log(response);     

            setStatus(response);
        }
    };
    xmlhttp.open("GET", "update_form.php", true);
    xmlhttp.send();
  }