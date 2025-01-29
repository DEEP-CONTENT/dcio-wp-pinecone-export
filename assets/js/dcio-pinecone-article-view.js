(function () {
  "use strict";

  const excludedPosts = document.querySelectorAll(".exclude_pinecone_export");
  const exportedPosts = document.querySelectorAll(".dcio_pinecone_exported");

  if (excludedPosts.length === 0 || exportedPosts.length === 0) return;

  excludedPosts.forEach((post) => {
    post.addEventListener("click", handleCLickExcludedPosts);
  });

  exportedPosts.forEach((post) => {
    post.addEventListener("click", handleCLickExportedPosts);
  });

  function uploadDeletePost(postId, action) {
    fetch(dciopineconeExport.ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "dcio",
        post_id: postId,
        pinecone_action: action,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        console.log(data);
        if (data.status === "error") {
          console.log("Error exporting post with ID " + postId);
        }
      });
  }

  function handleCLickExcludedPosts(e) {
    const postId = parseInt(e.target.value);
    const action = e.target.checked ? "exclude-on" : "exclude-off";
    uploadDeletePost(postId, action);

    const excludedChecbox = e.target;
    const exportedCheckbox = document.querySelector(
      `input.dcio_pinecone_exported[value="${postId}"]`
    );

    if (excludedChecbox.checked && exportedCheckbox.checked) {
      exportedCheckbox.checked = false;
    } else if (!excludedChecbox.checked && !exportedCheckbox.checked) {
      exportedCheckbox.checked = true;
    }
  }

  function handleCLickExportedPosts(e) {
    const postId = parseInt(e.target.value);
    const action = e.target.checked ? "add" : "delete";

    const excludedChecbox = document.querySelector(
      `input.exclude_pinecone_export[value="${postId}"]`
    );
    if (excludedChecbox.checked && e.target.checked) {
      e.target.checked = false;
      return;
    }

    uploadDeletePost(postId, action);
  }
})();
