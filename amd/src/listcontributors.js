define([], function() {
    return {
        init: function(listcontributors) {
            const listcontributorsElements = document.querySelectorAll('.listcontributors');
            listcontributorsElements.forEach(element => {
                element.innerHTML = listcontributors;
            });
        }
    };
});
