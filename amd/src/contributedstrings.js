define([], function() {
    return {
        init: function(contributedstrings) {
            const contributedstringsElements = document.querySelectorAll('.contributedstrings');
            contributedstringsElements.forEach(element => {
                element.textContent = contributedstrings;
            });
        }
    };
});
