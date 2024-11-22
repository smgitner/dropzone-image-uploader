Dropzone.autoDiscover = false;

document.addEventListener("DOMContentLoaded", function () {
    // Initialize Dropzone only once
    if (!Dropzone.instances.some((instance) => instance.element.id === "dropzoneUploader")) {
        const dropzoneInstance = new Dropzone("#dropzoneUploader", {
            url: dropzoneUploader.ajaxUrl, // AJAX URL from localized script
            paramName: "file", // The name used to transfer the file
            maxFilesize: 5, // Max file size in MB
            acceptedFiles: "image/*", // Only allow image uploads
            autoProcessQueue: false, // Disable automatic upload
            uploadMultiple: true, // Upload all files in one request
            parallelUploads: 10, // Allow up to 10 files in parallel
            addRemoveLinks: true, // Allow file removal
            init: function () {
                const dz = this;

                // Trigger upload when the button is clicked
                document
                    .getElementById("uploadButton")
                    .addEventListener("click", function () {
                        if (dz.getQueuedFiles().length > 0) {
                            dz.processQueue(); // Process the queue
                        } else {
                            document.getElementById("uploadFeedback").innerHTML =
                                "<p>No files to upload.</p>";
                        }
                    });

                // Add additional form data for the request
                dz.on("sendingmultiple", function (files, xhr, formData) {
                    formData.append("action", "dropzone_upload");
                    formData.append("security", dropzoneUploader.nonce);
                    formData.append(
                        "category",
                        document.getElementById("image-category").value
                    );
                    formData.append(
                        "assignment_slug",
                        document.getElementById("assignment-slug").value
                    );
                });

                // Handle successful uploads
                dz.on("successmultiple", function (files, response) {
                    if (response.success) {
                        document.getElementById("uploadFeedback").innerHTML =
                            `<p>${response.data.message}</p>`;
                        dz.removeAllFiles(true); // Clear the queue
                    } else {
                        document.getElementById("uploadFeedback").innerHTML =
                            `<p>Error: ${response.data.message}</p>`;
                    }
                });

                // Handle errors
                dz.on("errormultiple", function (files, errorMessage) {
                    document.getElementById("uploadFeedback").innerHTML =
                        `<p>Error: ${errorMessage}</p>`;
                });
            },
        });
    }
});