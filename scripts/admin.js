function backupDB() {
    const button = document.getElementById("backupDB");
    button.disabled = true;
  
    fetch('endpoints/db/backup.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const link = document.createElement('a');
          const filename = data.file;
          link.href = '.tmp/' + filename;
          link.download = 'backup.zip';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
  
          button.disabled = false;
        } else {
          showErrorMessage(data.errorMessage);
          button.disabled = false;
        }
      })
      .catch(error => {
        showErrorMessage(error);
        button.disabled = false;
      });
  }
  
  function openRestoreDBFileSelect() {
    document.getElementById('restoreDBFile').click();
  };
  
  function restoreDB() {
    const input = document.getElementById('restoreDBFile');
    const file = input.files[0];
  
    if (!file) {
      console.error('No file selected');
      return;
    }
  
    const formData = new FormData();
    formData.append('file', file);
  
    fetch('endpoints/db/restore.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message)
        window.location.href = 'logout.php';
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => showErrorMessage('Error:', error));
  }