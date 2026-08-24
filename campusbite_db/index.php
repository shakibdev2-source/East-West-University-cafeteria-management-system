<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CampusBite | University Cafeteria</title>


<style>


*{

margin:0;

padding:0;

box-sizing:border-box;

font-family:Arial,sans-serif;

}



body{

min-height:100vh;

background:linear-gradient(

135deg,

#2563eb,

#10b981

);

overflow:hidden;

opacity:0;

transition:0.5s;

}



/* Floating Circles */


.circle{

position:absolute;

border-radius:50%;

background:rgba(255,255,255,0.15);

animation:float 8s infinite ease-in-out;

}



.circle:nth-child(1){

width:180px;

height:180px;

top:10%;

left:5%;

}



.circle:nth-child(2){

width:250px;

height:250px;

bottom:5%;

right:5%;

animation-delay:2s;

}



.circle:nth-child(3){

width:120px;

height:120px;

top:50%;

right:30%;

animation-delay:4s;

}



@keyframes float{


0%,100%{

transform:translateY(0);

}


50%{

transform:translateY(-30px);

}


}




/* Navbar */


.navbar{

width:100%;

padding:20px 60px;

display:flex;

justify-content:space-between;

align-items:center;

color:white;

position:relative;

z-index:2;

}



.logo{

font-size:32px;

font-weight:bold;

}



.navbar a{

text-decoration:none;

color:white;

margin-left:20px;

}



/* Hero */


.hero{

height:80vh;

display:flex;

justify-content:center;

align-items:center;

text-align:center;

color:white;

position:relative;

z-index:2;

}



.content{

animation:slide 1.5s ease;

}



@keyframes slide{


from{

opacity:0;

transform:translateY(50px);

}


to{

opacity:1;

transform:translateY(0);

}


}



.content h1{

font-size:55px;

margin-bottom:20px;

}



.content span{

color:#fde047;

}



.content p{

font-size:20px;

max-width:650px;

line-height:1.6;

margin:auto;

}



.buttons{

margin-top:35px;

}



.btn{

display:inline-block;

padding:15px 35px;

border-radius:30px;

text-decoration:none;

margin:10px;

font-size:18px;

transition:.3s;

}



.login{

background:white;

color:#2563eb;

}



.register{

background:#facc15;

color:#111;

}



.btn:hover{

transform:scale(1.1);

}





.features{

display:flex;

justify-content:center;

gap:20px;

margin-top:40px;

flex-wrap:wrap;

}



.box{

background:rgba(255,255,255,0.15);

padding:20px;

width:220px;

border-radius:15px;

backdrop-filter:blur(10px);

}



.box h3{

margin-bottom:10px;

}



</style>


</head>


<body>



<div class="circle"></div>

<div class="circle"></div>

<div class="circle"></div>




<!-- Navbar -->


<div class="navbar">


<div class="logo">

🍔 CampusBite

</div>



<div>


<a href="login.php">

Login

</a>



<a href="register.php">

Register

</a>


</div>


</div>





<!-- Hero Section -->


<div class="hero">


<div class="content">


<h1>

Welcome To

<span>

CampusBite

</span>

</h1>



<p>

A smart University Cafeteria Management System

where students, faculty, staff and admin can manage

food ordering easily.

</p>




<div class="buttons">


<a class="btn login" href="login.php">

🔐 Login

</a>



<a class="btn register" href="register.php">

📝 Register

</a>


</div>





<div class="features">


<div class="box">


<h3>

👨‍🎓 Student

</h3>


<p>

Order food and track your orders

</p>


</div>




<div class="box">


<h3>

👨‍🏫 Faculty

</h3>


<p>

Easy cafeteria ordering

</p>


</div>




<div class="box">


<h3>

👨‍🍳 Staff

</h3>


<p>

Manage food serving

</p>


</div>




<div class="box">


<h3>

👨‍💼 Admin

</h3>


<p>

Complete system control

</p>


</div>



</div>



</div>


</div>





<!-- JavaScript -->


<script src="js/script.js"></script>


</body>


</html>