You can generate an S/MIME key and certificate in Linux using **OpenSSL**.
Here’s a step-by-step guide:

---

### **1. Generate a private key**

```bash
openssl genrsa -out smime.key 2048
```

* `2048` → RSA key length (can use 4096 for stronger security).
* Output: `smime.key` (your private key).

---

### **2. Create a certificate signing request (CSR)**

```bash
openssl req -new -key smime.key -out smime.csr
```

It will ask for:

* Country, State, Organization, etc.
* **Common Name (CN)** → Use your email address for S/MIME.
* Email Address → Also fill in your email.

---

### **3. Self-sign the certificate** (valid for 1 year)

```bash
openssl x509 -req -days 365 -in smime.csr -signkey smime.key -out smime.crt
```

* Output: `smime.crt` (public certificate).

---

### **4. Verify certificate**

```bash
openssl x509 -in smime.crt -text -noout
```

---

**Resulting files:**

* `smime.key` → Private key (keep secure)
* `smime.crt` → Public certificate
