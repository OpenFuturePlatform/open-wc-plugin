var iterations = 1000;
var salt = "NaCl";
var iv = "IIIIIIIIIIIIIIII";

function decrypt(encryptedText, pass) {

  var key = CryptoJS.PBKDF2(pass, CryptoJS.enc.Utf8.parse(salt), {
    keySize: 256 / 32,
    hasher: CryptoJS.algo.SHA256,
    iterations: iterations
  });

  var cipherParams = CryptoJS.lib.CipherParams.create(
    { ciphertext: CryptoJS.enc.Hex.parse(encryptedText) }
  );

  var decrypted = CryptoJS.AES.decrypt(cipherParams, key, {
    iv: CryptoJS.enc.Utf8.parse(iv),
    mode: CryptoJS.mode.CTR,
    padding: CryptoJS.pad.NoPadding

  })

  return decrypted.toString(CryptoJS.enc.Utf8);
}

function myFunction(encryptedText) {
 
  (async () => {

    const { value: formValues } = await Swal.fire({
      title: 'Enter password',
      html: '<input id="swal-input" class="swal2-input" required="true">',
      focusConfirm: false,
      preConfirm: () => {
        return [
          document.getElementById('swal-input').value
        ]
      }
    })
  
    if (formValues) {
      if (encryptedText == ''){
        Swal.fire(
          'Not encrypted address!',
          '',
          'error'
        )
      } else {
        try {
          var decrypted = decrypt(encryptedText, formValues[0]);
          Swal.fire(
            'Your decrypted key!',
            decrypted,
            'success'
          )
        } catch (error) {
          console.error(error);
          Swal.fire(
            'Invalid Password!',
            '',
            'error'
          )
        }
      }
      
    
    }
  
    })()
}