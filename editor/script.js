function allowDrop(event) {
    event.preventDefault();
}

function drag(event) {
    event.dataTransfer.setData("text", event.target.id);
}

function drop(event) {
    event.preventDefault();

    var data = event.dataTransfer.getData("text");
    var source = document.getElementById(data);
    var clone = source.cloneNode(true);

    if (source.classList.contains('dropped')) {
        source.remove();
    }

    clone.id = source.id + " clone" + Math.random();
    clone.classList.add("dropped");
    clone.draggable = true;
    clone.onclick = function () {
        this.remove();
    };
    if (event.target.classList.contains('dropped') && event.target.parentNode != null) {
        event.target.parentNode.appendChild(clone);
    } else {
        event.target.appendChild(clone);
    }
    setUnsavedData();
}

function cancel() {
    window.location.href = "../";
}

function changePlan(plan) {
    window.location.href = "./?plan=" + plan;
}

function remove(element) {
    element.remove();
    setUnsavedData();
}

function save() {
    let blob = createBlob();
    const downloadLink = document.createElement("a");

    downloadLink.href = URL.createObjectURL(blob);
    if (document.getElementById("plan").innerHTML == 'LGÖ') {
        downloadLink.download = "bereitschaftsplan.csv";
    } else {
        downloadLink.download = "bereitschaftsplanmusikschule.csv";
    }
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    closePopup('speichernpopup');
    setUnsavedData(false);
}

function showPopup(id) {
    document.getElementById(id).style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
}

function closePopup(id) {
    document.getElementById(id).style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}

function clearPlan() {
    var lis = document.getElementById("stundenraster").getElementsByTagName("li");
    while (lis.length > 0) {
        for (let i = 0; i < lis.length; i++) {
            lis[i].remove();
        }
        lis = document.getElementById("stundenraster").getElementsByTagName("li");
    }
    setUnsavedData(false);
}

function setUnsavedData(state) {
    if (state) {
        document.getElementById('unsavedData').innerHTML = 1;
        document.getElementById('btncancel').innerHTML = "Abbrechen";
    } else {
        document.getElementById('unsavedData').innerHTML = 0;
        document.getElementById('btncancel').innerHTML = "Zurück";
    }
}

function setNewPlan() {
    let blob = createBlob();
    let formData = new FormData();
    let blobfile;
    if (document.getElementById("plan").innerHTML == 'LGÖ') {
        blobfile = new File([blob], 'bereitschaftsplan.csv');
    } else {
        blobfile = new File([blob], 'bereitschaftsplanmusikschule.csv');
    }
    formData.append('datei', blobfile);
    let xhr = new XMLHttpRequest();
    xhr.open('POST', '../', true);
    xhr.send(formData);
    setUnsavedData(false);
    closePopup('speichernpopup');
}

function createBlob() {
    let data = document.getElementById("plan").innerHTML;
    data += ";Montag;Dienstag;Mittwoch;Donnerstag;Freitag;\n";
    var table = document.getElementById("stundenraster");
    var trs = table.getElementsByTagName("tr");

    for (let i = 1; i < trs.length + 2; i++) {

        if (i == 4) {
            data += "1. Pause;";
            var tds = trs[i - 1].getElementsByTagName("td");
        } else if (i == 7) {
            data += "2. Pause;";
            var tds = trs[i - 1].getElementsByTagName("td");
        } else if (i > 7) {
            data += i - 2 + ". Stunde;";
            var tds = trs[i - 2].getElementsByTagName("td");
        } else if (i > 4) {
            data += i - 1 + ". Stunde;";
            var tds = trs[i - 1].getElementsByTagName("td");
        } else {
            data += i + ". Stunde;";
            var tds = trs[i].getElementsByTagName("td");
        }


        for (let j = 1; j < tds.length; j++) {

            var lis = tds[j].getElementsByTagName("li");

            for (let k = 0; k < lis.length; k++) {

                if (k == 0) {
                    data += lis[k].id.split(" ")[0];
                } else {
                    data += ":" + lis[k].id.split(" ")[0];
                }
            }
            data += ";";
        }
        data += "\n";
    }

    let blob = new Blob([data], { type: "text:csv;charset=utf-8;" });
    return blob;
}

window.addEventListener('beforeunload', function (e) {
    if (document.getElementById('unsavedData').innerHTML == "1") {
        e.returnValue = "Sie haben noch ungespeicherte Daten!";
        return "Sie haben noch ungespeicherte Daten!";
    }
});
