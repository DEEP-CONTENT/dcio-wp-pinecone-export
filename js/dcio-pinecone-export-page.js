(function () {
  "use strict";

  const progressText = document.querySelector("#progress-text");
  const progressCounter = document.querySelector("#progress-count");
  const exportButton = document.querySelector(".dcio-export-pinecone__button");

  if (!exportButton) return;

  exportButton.addEventListener("click", handleCLickExport);

  function updateHtml(postId, sucess) {
    let counter = parseInt(progressCounter.textContent);
    progressCounter.innerHTML = counter + 1;

    const postElement = document.createElement("div");
    postElement.classList.add("post");

    if (!sucess) {
      postElement.classList.add("error");
      postElement.innerHTML = `
      <div>Post with ID ${postId} not exported</div>
    `;
      progressText.appendChild(postElement);
    } else {
      postElement.innerHTML = `
      <div>Post with ID ${postId} exported</div>
    `;
      progressText.appendChild(postElement);
    }
    progressText.appendChild(postElement);

    progressText.appendChild(postElement);
  }

  function exportPost(postId, counter) {
    fetch(dciopineconeExport.ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "dcio",
        post_id: postId,
        pinecone_action: "add",
        // Add any other data you want to send here
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        // Handle the response here
        console.log(data);
        if (!data.status === "error") {
          console.log("Error exporting post with ID " + postId);
        }
        updateHtml(postId, data.success);
      });
  }

  function handleCLickExport() {
    const length = dciopineconeExport.postsIdsToExport.length;

    for (let i = 0; i < length; i++) {
      let postId = dciopineconeExport.postsIdsToExport[i];
      exportPost(postId);
    }

    exportButton.remove();
  }
})();
