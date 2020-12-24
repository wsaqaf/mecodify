
//more functions

$(document).ready(function() {
	toggle_login();
	return true;
});


function toggle_login(preserve)
 {
	 if (typeof preserve === 'undefined') preserve = 0;
//	 alert('toggling!');
	  $.ajax({url:"login.php?action=toggle_login&page=tweets&login=1",
				complete: function (response) {
					$(".login_status").html(response.responseText);
					if (response.responseText.indexOf("Login") >= 0)
						{
							document.getElementById('left_menu').style.display = 'none';
						}
					else {
						document.getElementById('left_menu').style.display = 'block';
						   }
					},
				error: function () {$(".login_status").html(''); }
			 });

 }

function showtip(field)
  {
 //	 alert('toggling!');
 	  $.ajax({url:'login.php?action=tip&field='+field,
 				complete: function (response) { $('.tip').html(response.responseText); },
 				error: function () {$('.tip').html(''); }
 			 });
  }

function hidetip(field) { $('.tip').html(''); }

		$(document).ready(function(){
		  $(".slidingDiv").show();
		  $(".show_hide").addClass("minus").show();
		  $('.show_hide').toggle(
		      function(){
		          $(".slidingDiv").slideDown();
		          $(this).addClass("minus");
		          $(this).removeClass("plus");
		      },
		      function(){
		          $(".slidingDiv").slideUp();
		          $(this).addClass("plus");
		          $(this).removeClass("minus");
		      }
		  );
		});

		$(document).ready(function(){
		  $(".slidingDiv2").show();
		  $(".show_hide2").addClass("minus").show();
		  $('.show_hide2').toggle(
		      function(){
		          $(".slidingDiv2").slideDown();
		          $(this).addClass("minus");
		          $(this).removeClass("plus");
		      },
		      function(){
		          $(".slidingDiv2").slideUp();
		          $(this).addClass("plus");
		          $(this).removeClass("minus");
		      }
		  );
		});

			$(document).ready(function(){
			  $(".slidingDiv3").show();
			  $(".show_hide3").addClass("minus").show();
			  $('.show_hide3').toggle(
			      function(){
			          $(".slidingDiv3").slideDown();
			          $(this).addClass("minus");
			          $(this).removeClass("plus");
			      },
			      function(){
			          $(".slidingDiv3").slideUp();
			          $(this).addClass("plus");
			          $(this).removeClass("minus");
			      }
			  );
			});

					$(document).ready(function(){
					  $(".slidingDiv4").show();
					  $(".show_hide4").addClass("minus").show();
					  $('.show_hide4').toggle(
					      function(){
					          $(".slidingDiv4").slideDown();
					          $(this).addClass("minus");
					          $(this).removeClass("plus");
										add_date_details();
					      },
					      function(){
					          $(".slidingDiv4").slideUp();
					          $(this).addClass("plus");
					          $(this).removeClass("minus");
					      }
					  );
					});
									$(document).ready(function(){
									  $(".slidingDiv5").hide();
									  $(".show_hide5").addClass("plus").show();
									  $('.show_hide5').toggle(
									      function(){
									          $(".slidingDiv5").slideDown();
									          $(this).addClass("minus");
									          $(this).removeClass("plus");
														add_date_details();
									      },
									      function(){
									          $(".slidingDiv5").slideUp();
									          $(this).addClass("plus");
									          $(this).removeClass("minus");
									      }
									  );
									});

									$(document).ready(function(){
									  $(".slidingDiv6").hide();
									  $(".show_hide6").addClass("plus").show();
									  $('.show_hide6').toggle(
									      function(){
									          $(".slidingDiv6").slideDown();
									          $(this).addClass("minus");
									          $(this).removeClass("plus");
														add_date_details();
									      },
									      function(){
									          $(".slidingDiv6").slideUp();
									          $(this).addClass("plus");
									          $(this).removeClass("minus");
									      }
									  );
									});

		$(document).ready(function() {
		    $('input[type="radio"]').change(function() {
		        var rad = $(this).attr('id');
		            $('#' + rad + 'Div').show();
		            $('#' + rad + 'Div').siblings('div').hide();
		    });
		});

		 function getRadioValue(theRadioGroup)
		 {
		     var elements = document.getElementsByName(theRadioGroup);
		     for (var i = 0, l = elements.length; i < l; i++)
		     {
		         if (elements[i].checked)
		         {
		             return elements[i].value;
		         }
		     }
		 }

 		 function getBoxValue(theBoxGroup)
 		 {
//			 alert("checking "+theBoxGroup);
 		     var element = document.getElementById(theBoxGroup);
				 if (element.checked) return element.value;
 				 return "";
 		 }

		 function getSelectedValue(theBoxGroup)
 		 {
			 var elements = document.getElementsByName(theBoxGroup);
			 for (var i = 0, l = elements.length; i < l; i++)
			 {
							 if (elements[i].value)
							 		return elements[i].value;
			 }
			 return "";
 		 }


		$(document).ready(function() {
			case_proc();
		});

				function case_proc(action,case_id,email)
		 		 {
					if (typeof action === 'undefined') action = '';
					if (typeof case_id === 'undefined') case_id = '';
					if (typeof email === 'undefined') email = '';

					if (action=="more_info")
						{
							var sel = document.getElementById('case');
			      	var c = sel.options[sel.selectedIndex].value;
							url='login.php?action=more_info&case='+c;
							$.ajax({url: url,
											complete: function (response)
												{
												$('#chartcontainer').html(response.responseText);
												},
											error: function ()
												{
													$('#chartcontainer').html('error!');
												}
										 });
										 $('#tweetcontainer').html('');
							return true;
						}
					else if (action=="forgot")
						{
							if (!document.getElementById('email').value)
								{
									$('.email').html('<font color=red>Email required!</font>');
									return true;
								}
							else
								{
									$('.email').html('');
									var email=document.getElementById("email").value;
								}
								$('#tweetcontainer').html('');
						}
					else if (action=="create_account" || action=="update_account")
						{
							var name=document.getElementById("name").value;
							var email=document.getElementById("email").value;
							var title=document.getElementById("title").value;
							var institution=document.getElementById("institution").value;
							var country=document.getElementById("country").value;
							var password=document.getElementById("password").value;
							var password2=document.getElementById("password2").value;
							var required=['name','email','password','password2'];
							for (var i = 0, l = required.length; i < l; i++)
		 		     		{
									if (!document.getElementById(required[i]).value)
										{
											$('.'+required[i]).html('<font color=red>Field required!</font>');
											return true;
										}
								  else $('.'+required[i]).html('');
								}
							var re = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
							if (!re.test(email))
							{
									$('.email').html('<font color=red>Invalid email format</font>');
									return true;
							}
							if (document.getElementById('password').value!=document.getElementById("password2").value)
								{
										$('.password2').html('<font color=red>Password mismatch</font>');
										return true;
								}
							else $('.password2').html('');
							if (!document.getElementById('terms').checked)
								{
									$('.terms').html('<font color=red>You have to agree to the terms to create an account.</font>');
									return true;
								}
			  			$.ajax({type: 'POST', url: 'login.php',
											data: { action: action, name: name, email: email, title: title, institution: institution, country: country, password: password},
		    							complete: function(response)
												{
		//											alert('success!');
			    								$( "#chartcontainer").html(response.responseText);
												},
											error: function ()
				 								{
													alert('error!');
													$('#chartcontainer').html('error!');
				 								}
			  				});
								$('#tweetcontainer').html('');
								return true;
						}
					else if (action=="login")
						{
							var email=document.getElementById("email").value;
							var password=document.getElementById("password").value;
							var ajax = $.ajax({type: 'POST', url: 'login.php',
											data: { action: 'login', email: email, password: password},
		    							complete: function(response)
												{
		//											alert('success!');
			    								$( "#chartcontainer").html(response.responseText);
if (!(response.responseText.startsWith('Incorrect'))) { location.reload(); }
					},
											error: function ()
				 								{
		//											alert('error!');
													$('#chartcontainer').html('error!');
				 								}
			  				});
								ajax.fail(function (jqXHr, textStatus, errorThrown) {
								    $("#chartcontainer").html(jqXHr.responseText);
								});
                                        //toggle_login();
                                        //$('#tweetcontainer').html('');
							return true;
						}
				else if (action=="logout")
							{
					 	    $.ajax({url:'login.php?action=logout',
					 	            complete: function (response)
					 	              {
					 //									alert('url:'+url);
					 								$('#chartcontainer').html(response.responseText);
													toggle_login();
					 	              },
					 	            error: function ()
					 								{
					 									$('#chartcontainer').html('error!');
					 								}
					 	           });
											 location.reload();
											 document.getElementById('left_menu').style.display = 'none';
											 return true;
							}
                                else if (action=="submit_case" || action=="resubmit_case")
                                        {
                                                var case_id=document.getElementById("case_id").value;
if( /[^a-zA-Z0-9_]/.test(case_id) ) {
       alert('The case ID has to be alphanumeric (only alphabets or numbers and no space)');
       return false;
    }
		var case_name=document.getElementById("case_name").value;
		if (!case_name) { alert('The case name cannot be blank'); return false; }
		var case_platform=document.getElementById("case_platform").value;
		var case_include_retweets=getBoxValue("case_include_retweets");
		if (case_include_retweets=="on") case_include_retweets=1; else case_include_retweets=0;
		var case_top_only=getBoxValue("case_top_only");
		if (case_top_only=="on") case_top_only=1; else case_top_only=0;
		var case_query=document.getElementById("case_query").value;
		if (!case_query) { alert('The case query cannot be blank'); return false; }
		var case_from=document.getElementById("case_from").value;
		var case_to=document.getElementById("case_to").value;
		var case_details=document.getElementById("case_details").value;
		var case_details_url=document.getElementById("case_details_url").value;
		var case_flags=document.getElementById("case_flags").value;
		var case_private=document.getElementById("case_private").value;
		var email=document.getElementById("email").value;
		var ajax = $.ajax({type: 'POST', url: 'login.php',
						data: { action: action, email: email, case_id: case_id, case_name: case_name, case_platform: case_platform,
														case_include_retweets: case_include_retweets, case_top_only: case_top_only, case_query: case_query, case_from: case_from, case_to: case_to,
														case_details: case_details, case_details_url: case_details_url, case_flags: case_flags, case_private: case_private},
              complete: function(response)
                                      {
//                                                                                      alert('success!');
                              $( "#chartcontainer").html(response.responseText);
                                      },
              error: function ()
                      {
//                                                                                      alert('error!');
                              $('#chartcontainer').html('error!');
                      }
          });
                  ajax.fail(function (jqXHr, textStatus, errorThrown) {
                      $("#chartcontainer").html(jqXHr.responseText);
                  });
		          if (action=="submit_case") { toggle_login(); }
		          $('#tweetcontainer').html('');
		          return false;
            }
					else if (action=="delete_case")
								{
									if (!confirm("Are you sure you want to delete case "+case_id+" ?")) return;
						 	    $.ajax({url:'login.php?action=delete_case&case_id='+case_id+'&email='+email ,
						 	            complete: function (response)
						 	              {
						 //									alert('url:'+url);
						 								$('#chartcontainer').html(response.responseText);
														toggle_login();
						 	              },
						 	            error: function ()
						 								{
						 									$('#chartcontainer').html('error!');
						 								}
						 	           });
												 $('#tweetcontainer').html('');
												 return true;
								}
					else if (action=="empty_case")
								{
									if (!confirm("Are you sure you want to empty case "+case_id+" ?")) return;
						 	    $.ajax({url:'login.php?action=empty_case&case_id='+case_id+'&email='+email ,
						 	            complete: function (response)
						 	              {
						 //									alert('url:'+url);
						 								$('#chartcontainer').html(response.responseText);
														toggle_login();
						 	              },
						 	            error: function ()
						 								{
						 									$('#chartcontainer').html('error!');
						 								}
						 	           });
												 $('#tweetcontainer').html('');
												 return false;
								}
					else if (action=="edit_case")
								{
						 	    $.ajax({url:'login.php?action=edit_case&case_id='+case_id+'&email='+email ,
						 	            complete: function (response)
						 	              {
						 //									alert('url:'+url);
						 								$('#chartcontainer').html(response.responseText);
						 	              },
						 	            error: function ()
						 								{
						 									$('#chartcontainer').html('error!');
						 								}
						 	           });
												 $('#tweetcontainer').html('');
												 return true;
								}
						else if (action=="delete_account")
									{
										if (!confirm("Are you sure you want to delete your account?")) return;
							 	    $.ajax({url:'login.php?action=delete_account&email='+email ,
							 	            complete: function (response)
							 	              {
							 //									alert('url:'+url);
							 								$('#chartcontainer').html(response.responseText);
															toggle_login();
							 	              },
							 	            error: function ()
							 								{
							 									$('#chartcontainer').html('error!');
							 								}
							 	           });
													 $('#tweetcontainer').html('');
													 return true;
									}
						else if (action=="edit_profile")
									{
										url='login.php?action=edit_profile';
							 	    $.ajax({url: url,
							 	            complete: function (response)
							 	              {
		//					 									alert('url:'+url);
							 								$('#chartcontainer').html(response.responseText);
							 	              },
							 	            error: function ()
							 								{
							 									$('#chartcontainer').html('error!');
							 								}
							 	           });
													 $('#tweetcontainer').html('');
													 return true;
									}
				 url='login.php?action='+action+'&case_id&='+case_id+'&email='+email;
			    $.ajax({url:url,
			            complete: function (response)
			              {
		//									alert('url:'+url);
										$('#chartcontainer').html(response.responseText);
			              },
			            error: function ()
										{
										$('#chartcontainer').html('error!');
										}
			           });
					$('#tweetcontainer').html('');
					return true;
		 		 }

		 function visualize(network)
 	 		  {
 //	 				toggle_login(1);
 			if (typeof network === 'undefined') network = 0;

			var qry=[]; var table='';
			var sel = document.getElementById('case');
			table = sel.options[sel.selectedIndex].value;
			if (!table) { alert('Please select at least one case to visualise!'); return; }

			if (network)
				{
					var level=getSelectedValue('level');
//					var minimum_followers=document.getElementById("minimum_followers").value;
//					var url='fetch_tweeters.php?'+network+'=1&level='+level+'&minimum_followers='+minimum_followers+'&table='+table;

					var url='fetch_tweeters.php?'+getRadioValue(network)+'=1&level='+level+'&table='+table;
				}
			else
			 	{
					var params=["followers","retweets","responses","responders","all_mentions","mention","quotes","tweets"];
					var limit=getSelectedValue('limit');
					var lang=document.getElementById("language").value;
					var loc=document.getElementById("location").value;
					var bio=document.getElementById("bio").value;

					var url='fetch_tweeters.php?overview=1&limit='+limit+'&table='+table;

					var t;var j=0;var i;for(i=0;i<params.length;++i){t=getBoxValue(params[i]);if(t){url=url+'&'+params[i]+'='+t; j++; }}
					if (lang) url=url+'&language='+lang;
					if (loc) url=url+'&location='+loc;
					if (bio) url=url+'&bio='+bio;

					if (!j) { alert("Please select at least one influence chart to show;"); return; }
				}
			jQuery("#loading").show();
			$('#chartcontainer').html('');

			$.ajax({url:url,
							complete: function (response)
								{
									jQuery("#loading").hide();
									$('#chartcontainer').html(response.responseText);
								},
							error: function () {$('#chartcontainer').html('error!'); jQuery("#loading").hide();}
						 });
			return true;
	  }

		function popitup(url) {
			newwindow=window.open(url,'name','height=600,width=900');
			if (window.focus) {newwindow.focus()}
			return false; }

function showkumu()
	  {
	    var e = document.getElementById("case");
	    var case_id = e.options[e.selectedIndex].value;
	    var url='tmp/kumu/'+case_id+'_users.csv';
      $.ajax({type:'POST',url:url,
              complete: function (response)
                {
									if (response.status == 200)
                   {   $('#kumu_users').html("<a href='"+url+"'>Tweeter CSV file</a>"); }
                },
              error: function () { $('#kumu_users').html("No Kumu tweeter data."); }
             });
      var url1='tmp/kumu/'+case_id+'_responses.csv';
      $.ajax({type:'POST',url:url1,
              complete: function (response)
                {
                  if (response.status == 200)
                    {  $('#kumu_responses').html("<a href='"+url1+"'>Response CSV file</a>"); }
                },
              error: function () { $('#kumu_responses').html("No Kumu response data.");}
             });
      var url2='tmp/kumu/'+case_id+'_mentions.csv';
      $.ajax({type:'POST',url:url2,
              complete: function (response)
                {
                  if (response.status == 200)
  									{ $('#kumu_mentions').html("<a href='"+url2+"'>Mentions CSV file</a>");  }
                },
              error: function () { $('#kumu_mentions').html("No Kumu mention data.");}
             });
			 var url3='tmp/kumu/'+case_id+'_top_tweets_10000.csv';
       $.ajax({type:'POST',url:url3,
               complete: function (response)
                 {
                   if (response.status == 200)
	  									{ $('#kumu_top_10000').html("<a href='"+url3+"'>Top 10000 tweets CSV file</a>");  }
                 },
               error: function () { $('#kumu_top_10000').html("No Kumu top 10000 tweets data.");}
              });
			var url4='tmp/kumu/'+case_id+'_top_tweets_5000.csv';
      $.ajax({type:'POST',url:url4,
              complete: function (response)
                {
                  if (response.status == 200)
  									{ $('#kumu_top_5000').html("<a href='"+url4+"'>Top 5000 CSV file for Kumu import</a>");  }
                },
              error: function () { $('#kumu_top_5000').html("No Kumu top 5000 tweets data.");}
             });
		 var url5='tmp/kumu/'+case_id+'_top_tweets_1000.csv';
     $.ajax({type:'POST',url:url5,
             complete: function (response)
               {
                 if (response.status == 200)
  									{ $('#kumu_top_1000').html("<a href='"+url5+"'>Top 1000 CSV file for Kumu import</a>");  }
               },
             error: function () { $('#kumu_top_1000').html("No Kumu top 1000 tweets data.");}
            });
	  }

	  function GetDetails(section,url)
	  {
			$('#'+section).html('');
			jQuery("#loading").show();
	    $.ajax({url:url,
	            complete: function (response)
	              {
	                    $('#loading').html(response.responseText);
//											jQuery("#followers").show();
//											jQuery("#loading").hide();
	              },
	            error: function () {$('#'+section).html('error!');jQuery("#loading").hide(); }
	           });
	  }

		function ValidateDateTime(field)
				{
					 var dtValue=document.getElementById(field).value.trim();
					 var re = /^(\d{4}-\d{2}-\d{2})$/;
					 if (dtValue.match(re))
					 	{
							dtValue=dtValue+" 00:00:00";
							document.getElementById(field).value=dtValue;
						}
					 else {  }
				   var dtRegex = new RegExp(/^\d{4}-(0[1-9]|1[0-2])-([0-2]\d|3[01]) ([0-2][0-9]|):[0-5]\d:[0-5]\d$/);
					 if (!dtRegex.test(dtValue) && dtValue.length>0)
					 	{
							$('.'+field).html("<br><b>The datetime value ("+dtValue+") is invalid.</b>");
						}
					else { $('.'+field).html(""); }

				}
