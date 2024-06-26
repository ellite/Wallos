<!DOCTYPE html>
<html>

<head>
    <title>Subscription Logos</title>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            const searchForm = document.getElementById("search-form");
            const imageResults = document.getElementById("image-results");

            searchForm.addEventListener("submit", function (e) {
                e.preventDefault();

                const searchTerm = document.getElementById("search").value.trim();
                if (searchTerm === "") {
                    alert("Please enter a search term.");
                    return;
                }

                // Use the proxy to perform a Google image search
                const proxyUrl = `endpoints/logos/search.php?search=${searchTerm}`;

                // Send an AJAX request to the proxy
                fetch(proxyUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.imageUrls) {
                            // Display the image sources from the PHP response.
                            displayImageResults(data.imageUrls);
                        } else if (data.error) {
                            console.error(data.error);
                        }
                    })
                    .catch(error => {
                        console.error("Error fetching image results:", error);
                    });
            });

            function displayImageResults(imageSources) {
                // Clear previous results
                imageResults.innerHTML = "";

                // Display the image sources as image elements
                imageSources.forEach(src => {
                    const img = document.createElement("img");
                    img.src = src;
                    imageResults.appendChild(img);
                });
            }
        });
    </script>
</head>

<body>
    <form id="search-form">
        <input type="text" name="search" id="search">
        <input type="submit" value="Search">
    </form>
    <div id="image-results">
        <!-- Image results will be displayed here -->
    </div>
</body>

</html>