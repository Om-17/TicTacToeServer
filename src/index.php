<html>
<input type="text" name="" id="txt" onchange="txtchange(this)"/>
<script type="text/javascript">
  

     const ws = new WebSocket('ws://localhost:8080');
       ws.onopen = () => {
  console.log('WebSocket connection opened');
}
     ws.onmessage = (message) => {
  console.log('Message received from server:', message.data);
}
function txtchange(i){
  console.log(i.value, 'change');
  ws.send(i.value);



}




</script>
</html>