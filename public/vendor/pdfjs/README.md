# PDF.js (vendored)

`pdf.min.js` and `pdf.worker.min.js` are the official **PDF.js 3.11.174**
release builds, fetched from cdnjs:

```
https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js
https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js
```

- Project: https://github.com/mozilla/pdf.js
- License: Apache-2.0 (the notice is preserved in the file header)

They are vendored rather than pulled from a CDN at read time so the secure
viewer works on a bad connection, offline, and without a third-party
dependency between a student and the paper they paid for.

`resources/views/viewer.blade.php` loads `pdf.min.js` and points
`GlobalWorkerOptions.workerSrc` at `pdf.worker.min.js`;
`public/js/pdf-reader.js` does the rendering. To upgrade, replace both
files with the same version (the UMD `pdf.min.js` build is what the reader
expects — v4 and later ship ES modules only) and re-test the viewer.
