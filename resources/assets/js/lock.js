import { ready } from './ready';

ready(() => {
  const regexResult = /admin\/questions\/(\d+)/.exec(window.location.toString());

  if (regexResult && regexResult.length > 1) {
    const questionId = Number(regexResult[1]);
    window.addEventListener('beforeunload', () => {
      fetch('/admin/questions/'+questionId+'/unlock', {
        keepalive: true
      }).then(res => {
        console.log(res);
        res.text().then(txt => console.log(txt));
      }).catch(console.error);
    });
  }
});
