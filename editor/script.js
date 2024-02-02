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
    clone.id = "clone" + Math.random();
    clone.classList.add("dropped");
    clone.draggable = false;
    clone.onclick = function () {
        this.remove();
    };
    event.target.appendChild(clone);
}
