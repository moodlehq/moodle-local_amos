"use strict";

module.exports = function (grunt) {
    const sass = require('node-sass');

    grunt.initConfig({
        watch: {
            // Watch for any changes to less files and compile.
            files: ["scss/*.scss"],
            tasks: ["compile"],
            options: {
                spawn: false,
                livereload: true
            }
        },
        sass: {
            dist: {
                options: {
                    outputStyle: 'compressed',
                    sourceMap: false
                },
                files: {
                    "styles.css": "scss/styles.scss"
                }
            },
            options: {
                implementation: sass,
            },
        },
    });

    // Load contrib tasks.
    grunt.loadNpmTasks("grunt-contrib-watch");

    // Load core tasks.
    grunt.loadNpmTasks("grunt-sass");

    // Register tasks.
    grunt.registerTask("default", ["compile"]);
    grunt.registerTask("compile", ["sass"]);
};
