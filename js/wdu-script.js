let dropzoneInstance;

document.addEventListener("DOMContentLoaded", function () {
    // Initialize Dropzone with autoProcessQueue set to false
    dropzoneInstance = new Dropzone("#dropzoneUploader", {
        url: dropzoneUploader.ajaxUrl,
        paramName: "file",
        maxFilesize: 5, // Max file size in MB
        acceptedFiles: "image/*", // Only allow image uploads
        autoProcessQueue: false, // Disable automatic upload
        addRemoveLinks: true, // Allow file removal
        init: function () {
            this.on("sending", function (file, xhr, formData) {
                formData.append("action", "dropzone_upload");
                formData.append("security", dropzoneUploader.nonce);
                formData.append("category", document.getElementById("image-category").value);
                formData.append("assignment_slug", document.getElementById("assignment-slug").value);
            });

            this.on("success", function (file, response) {
                if (response.success) {
                    document.getElementById("uploadFeedback").innerHTML = `<p>${response.data.message}</p>`;
                    this.removeFile(file);
                } else {
                    document.getElementById("uploadFeedback").innerHTML = `<p>Error: ${response.data.message}</p>`;
                }
            });

            this.on("error", function (file, errorMessage) {
                document.getElementById("uploadFeedback").innerHTML = `<p>Error: ${errorMessage}</p>`;
            });
        },
    });

    // Trigger manual upload on button click
    document.getElementById("uploadButton").addEventListener("click", function () {
        dropzoneInstance.processQueue();
    });
});