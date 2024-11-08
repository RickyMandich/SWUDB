<!DOCTYPE html>
    <html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <title>Home</title>
    </head>
    <body>
        <span class="pageLink database">
            <a href="./carte" target="_blank">
                <img src="https://imgs.search.brave.com/NnxRTb4Th5NIbAhODYxF1mfGSsrGWQmMeNlXVck08J0/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly91cGxv/YWQud2lraW1lZGlh/Lm9yZy93aWtpcGVk/aWEvY29tbW9ucy90/aHVtYi9mL2Y2L05v/dW5fcHJvamVjdF9k/YXRhYmFzZV8xMjc2/NTI2X2NjLnN2Zy82/NDBweC1Ob3VuX3By/b2plY3RfZGF0YWJh/c2VfMTI3NjUyNl9j/Yy5zdmcucG5n" alt="">
                <br>
                DATABASE
            </a>
        </span>
        <span class="pageLink inputDeck">
            <a href="./insertToDeck" target="_blank">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSsVHhetQPbrMnemkmUqGzjuDIrJ86qoYjLpbJ-Vf7m4sEwaCdD" alt="">
                <br>
                INPUT DECK
            </a>
        </span>
        <span class="pageLink jsonDeck">
            <a href="./uploadJsonDeck" target="_blank">
                <img src="https://imgs.search.brave.com/4v057JpSYBXf9JUUEpNi8b8pQffnt95i0keWnk48h7k/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly90NC5m/dGNkbi5uZXQvanBn/LzA4LzIwLzAzLzk1/LzM2MF9GXzgyMDAz/OTU5NV8wTENMQzBx/cUx2SXVNcmtXTjI5/QWEyOU9NTng2bXhI/ZS5qcGc" alt="">
                <br>
                JSON DECK
            </a>
        </span>
        <span class="pageLink mazzo">
            <a href="./mazzi" target="_blank">
                <img src="https://imgs.search.brave.com/dVRz4pmn6Hgw2REoO-lvu8yARI4i5eSwEERefh3_YTM/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly93d3cu/c3RpY2tlcnBvaW50/Lml0L2ltYWdlcy9z/dGFyLXdhcnMtdW5s/aW1pdGVkLWRvdWJs/ZS1kZWNrLXBvZC1i/bHVlLmpwZw">
                <br>
                mazzi
            </a>
        </span>
        <span class="logo logo2">
            <img src="https://starwarsunlimited.com/_next/image?url=%2Fsplash%2Flogo%403x.png&w=96&q=75">
        </span>
        <span class="logo logo3">
            <img src="https://starwarsunlimited.com/_next/image?url=%2Fsplash%2Flogo%403x.png&w=96&q=75">
        </span>
    </body>

    <style>
        body{
            background-image: url("https://imgs.search.brave.com/CNHJWzk1nYPcuPCXw6SGBNXNGrTQPDCx9_2XcXqkygI/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9yYXJl/LWdhbGxlcnkuY29t/L21vY2FoYmlnLzc0/ODE5LVN0YXItV2Fy/cy1BbmFraW4tU2t5/d2Fsa2VyLU9iaS1X/YW4tS2Vub2JpLUFo/c29rYS5qcGc");
            background-repeat: no-repeat;
            background-size: cover;
        }
        :root{
            --bs-primary-rgb: #6495ed;
        }
        a{
            text-decoration: none;
            color: black;
        }
        .pageLink{
            border: 10px solid var(--bs-primary-rgb);
            background: var(--bs-primary-rgb);
            display: inline-block;
            text-align: center;
            transform: translate(-50%, -50%);
            border-radius: 5%;
        }
        .logo{
            display: inline-block;
            text-align: center;
            transform: translate(-50%, -50%);
            border-radius: 5%;
            background-image: url("https://imgs.search.brave.com/0wfCcBagoP_oWt7ERjjwyH2kz01iu6FzZNncMjKsBlU/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly93d3cu/bWV0ZW9yb2xvZ2lh/ZW5yZWQuY29tL3dw/LWNvbnRlbnQvdXBs/b2Fkcy8yMDIzLzA4/L2dhbGF4aWFzLWV4/dHJhbmFzLmpwZw");
            width: 25vh;
            height: 30vh;
            
        }
        span>img{
            transform: translate(0, 100%);
        }
        a>img{
            background: white;
            width: 25vh;
        }
        .database{
            position: absolute;
            left: 25vw;
            top: 25vh;
        }
        .inputDeck{
            position: absolute;
            left: 75vw;
            top: 25vh;
        }
        .jsonDeck{
            position: absolute;
            left: 50vw;
            top: 75vh;
        }
        .mazzo{
            position: absolute;
            left: 50vw;
            top: 25vh;
        }
        .logo2{
            position: absolute;
            left: 25vw;
            top: 75vh;
        }
        .logo3{
            position: absolute;
            left: 75vw;
            top: 75vh;
        }
    </style>
</html>