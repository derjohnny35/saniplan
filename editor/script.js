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
    event.target.appendChild(clone);
}

function cancel() {
    window.location.href = "../";
}

window.addEventListener('beforeunload', function (e) {
    if (document.getElementById('unsavedData').innerHTML == "1") {
        e.returnValue = "Sie haben noch ungespeicherte Daten!";
        return "Sie haben noch ungespeicherte Daten!";
    }
});
