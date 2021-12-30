$(document).ready(function() {
	toggle_login();
	return true;
});

function toggle_login(preserve)
 {
	 if (typeof preserve === 'undefined') preserve = 0;
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
 	  $.ajax({url:'login.php?action=tip&field='+field,
 				complete: function (response) { $('.tip').html(response.responseText); },
 				error: function () {$('.tip').html(''); }
 			 });
  }

function hidetip(field) { $('.tip').html(''); }

		$(document).ready(function(){
		  $(".slidingDiv").hide();
		  $(".show_hide").addClass("plus").show();
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
		  $(".slidingDiv2").hide();
		  $(".show_hide2").addClass("plus").show();
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
			  $(".slidingDiv3").hide();
			  $(".show_hide3").addClass("plus").show();
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
					  $(".slidingDiv4").hide();
					  $(".show_hide4").addClass("plus").show();
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

		var series_urls=[];
		// handles the click event, sends the query

		function getOutput(url1,div1)
		 {
		    $.ajax({url:url1,
		        	  complete: function (response) { $(div1).html(response.responseText); },
		        		error: function () {$(div1).html('error!'); }
							 });
			  return true;
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
					return false;
				}
			else if (action=="forgot")
				{
					if (!document.getElementById('email').value)
						{
							$('.email').html('<font color=red>Email required!</font>');
							return false;
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
									return false;
								}
						  else $('.'+required[i]).html('');
						}
					var re = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
					if (!re.test(email))
					{
							$('.email').html('<font color=red>Invalid email format</font>');
							return false;
					}
					if (document.getElementById('password').value!=document.getElementById("password2").value)
						{
								$('.password2').html('<font color=red>Password mismatch</font>');
								return false;
						}
					else $('.password2').html('');
					if (!document.getElementById('terms').checked)
						{
							$('.terms').html('<font color=red>You have to agree to the terms to create an account.</font>');
							return false;
						}
	  			$.ajax({type: 'POST', url: 'login.php',
									data: { action: action, name: name, email: email, title: title, institution: institution, country: country, password: password},
    							complete: function(response)
										{
	    								$( "#chartcontainer").html(response.responseText);
										},
									error: function ()
		 								{
											$('#chartcontainer').html('error!');
		 								}
	  				});
						$('#tweetcontainer').html('');
						return false;
				}
			else if (action=="login")
				{
					var email=document.getElementById("email").value;
					var password=document.getElementById("password").value;
					var ajax = $.ajax({
type: 'POST',
url: 'login.php',
ContentType : 'application/json',
data: { 'action': 'login', 'email': email, 'password': password},
    							complete: function(response)
										{
	    								$( "#chartcontainer").html(response.responseText);
if (!(response.responseText.startsWith('Incorrect'))) { location.reload(); }
										},
									error: function ()
		 								{
											$('#chartcontainer').html('error!');
		 								}
	  				});
						ajax.fail(function (jqXHr, textStatus, errorThrown) {
						    $("#chartcontainer").html(jqXHr.responseText);
						});
					return false;
				}
		else if (action=="logout")
					{
			 	    $.ajax({url:'login.php?action=logout',
			 	            complete: function (response)
			 	              {
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
									 return false;
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
		    								$( "#chartcontainer").html(response.responseText);
											},
										error: function ()
			 								{
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
			else if (action=="empty_case")
						{
							if (!confirm("Are you sure you want to empty case "+case_id+" ?")) return;
				 	    $.ajax({url:'login.php?action=empty_case&case_id='+case_id+'&email='+email ,
				 	            complete: function (response)
				 	              {
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
				 								$('#chartcontainer').html(response.responseText);
				 	              },
				 	            error: function ()
				 								{
				 									$('#chartcontainer').html('error!');
				 								}
				 	           });
										 $('#tweetcontainer').html('');
										 return false;
						}
				else if (action=="delete_account")
							{
								if (!confirm("Are you sure you want to delete your account?")) return;
					 	    $.ajax({url:'login.php?action=delete_account&email='+email ,
					 	            complete: function (response)
					 	              {
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
				else if (action=="edit_profile")
							{
								url='login.php?action=edit_profile';
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
											 return false;
							}
		 url='login.php?action='+action+'&case_id&='+case_id+'&email='+email;
	    $.ajax({url:url,
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
			return false;
 		 }

		 function add_date_details()
	 	 {
			 show_or_hide(document.getElementById('specify_period'),"block");
		   var sel = document.getElementById('case');
     	 var c = sel.options[sel.selectedIndex].value;
			 if (c)
			   {
			 			$.ajax({url:'cases.php?case='+c+'&q=period',
							 complete: function (response) { $("#daterange").html(response.responseText);  },
							 error: function () {$("#daterange").html('error!'); }
							});
							document.getElementById('startdate').disabled = false;
							document.getElementById('starttime').disabled = false;
							document.getElementById('enddate').disabled = false;
							document.getElementById('endtime').disabled = false;
				 }
			 else
			 {
						$('#daterange').html('Select country to enable fields!');
			 			document.getElementById('startdate').disabled = true;
						document.getElementById('starttime').disabled = true;
						document.getElementById('enddate').disabled = true;
						document.getElementById('endtime').disabled = true;
			 }
				 return true;
	 	 }

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
				 return "";
		 }

		 function getBoxValue(theBoxGroup)
		 {
		     var element = document.getElementById(theBoxGroup);
				 if (element.checked) return element.value;
				 return "";
		 }

	// Original JavaScript code by Chirp Internet: www.chirp.com.au

		   function checkDate(field)
		   {
		     var minYear = 1902;
		     var maxYear = (new Date()).getFullYear();

		     var errorMsg = "";

		     // regular expression to match required date format
		     re = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;

		     if(field.value != '') {
		       if(regs = field.value.match(re)) {
		         if(regs[1] < 1 || regs[1] > 31) {
		           errorMsg = "Invalid value for day: " + regs[1];
		         } else if(regs[2] < 1 || regs[2] > 12) {
		           errorMsg = "Invalid value for month: " + regs[2];
		         } else if(regs[3] < minYear || regs[3] > maxYear) {
		           errorMsg = "Invalid value for year: " + regs[3] + " - must be between " + minYear + " and " + maxYear;
		         }
		       } else {
		         errorMsg = "Invalid date format: " + field.value;
		       }
		     }
		     return errorMsg;
		   }

	function checkTime(field)
	{
		var errorMsg = "";

		// regular expression to match required time format
		re = /^(\d{1,2}):(\d{2})(:00)?([ap]m)?$/;

		if(field.value != '') {
			if(regs = field.value.match(re)) {
				if(regs[4]) {
					// 12-hour time format with am/pm
					if(regs[1] < 1 || regs[1] > 12) {
						errorMsg = "Invalid value for hours: " + regs[1];
					}
				} else {
					// 24-hour time format
					if(regs[1] > 23) {
						errorMsg = "Invalid value for hours: " + regs[1];
					}
				}
				if(!errorMsg && regs[2] > 59) {
					errorMsg = "Invalid value for minutes: " + regs[2];
				}
			} else {
				errorMsg = "Invalid time format: " + field.value;
			}
		 }
		return errorMsg;
	}
		function show_or_hide(elements,visible) {
		  var elements2 = elements.length ? elements : [elements];
		  for (var index = 0; index < elements2.length; index++) {
		    elements2[index].style.display = visible;
		    if (elements.id=="specify_types" && visible=="none")
			{
			 document.getElementById("image_tweets").checked=false;
       document.getElementById("video_tweets").checked=false;
       document.getElementById("link_tweets").checked=false;
       document.getElementById("user_verified").checked=false;
       document.getElementById("retweet_tweets").checked=false;
       document.getElementById("response_tweets").checked=false;
       document.getElementById("responded_tweets").checked=false;
       document.getElementById("quoting_tweets").checked=false;
       document.getElementById("referenced_tweets").checked=false;
       document.getElementById("mentions_tweets").checked=false;
       document.getElementById("any_hashtags").value="";
       document.getElementById("any_keywords").value="";
       document.getElementById("all_keywords").value="";
       document.getElementById("exact_phrase").value="";
       document.getElementById("from_accounts").value="";
       document.getElementById("in_reply_to_tweet_id").value="";
       document.getElementById("location").value="";
       document.getElementById("min_retweets").value="";
}
  if (elements.id=="specify_period" && visible=="none")
      {
       document.getElementById("startdate").value="";
       document.getElementById("starttime").value="";
       document.getElementById("enddate").value="";
       document.getElementById("endtime").value="";
}
  if (elements.id=="specify_lang" && visible=="none")
      {
       document.getElementById("language").value="";
      }
  if (elements.id=="specify_client" && visible=="none")
      {
       document.getElementById("web_client").checked=false;
       document.getElementById("android").checked=false;
       document.getElementById("iphone").checked=false;
       document.getElementById("mobile_web").checked=false;
       document.getElementById("tweetdeck").checked=false;
       document.getElementById("blackberry").checked=false;
       document.getElementById("ipad").checked=false;
       document.getElementById("twitter_for_websites").checked=false;
       document.getElementById("facebook").checked=false;
       document.getElementById("tweetcaster_for_android").checked=false;
       document.getElementById("other_source").value="";
			}
		  }
		}

		function popitup(url) {
			newwindow=window.open(url,'name','height=600,width=900');
			if (window.focus) {newwindow.focus()}
			return false; }

function showkumu()
          {
	    return;
          }

	  function GetDetails(url,user_name,clear_text,branch)
	  {
			if (typeof user_name === 'undefined') user_name = ''; else user_name='&user_name='+encodeURIComponent(user_name);
			if (typeof branch === 'undefined') branch = ''; else branch_part='&branch='+branch;
			if (typeof clear_text==='undefined') clear_text=''; else clear_text='&clear_text='+encodeURIComponent(clear_text);
			url=url+user_name+clear_text;
	    if (!branch)
				{
					jQuery("#loadingtweets").show();
					$('#tweetcontainer').html('');
				}
			else
			  {
					$('#'+branch).html('<br><center>Loading replies... <img src="images/ajax-loader.gif"><br></center>');
					url=url+branch_part;
				}
	    $.ajax({url:url,
	            complete: function (response)
	              {
									if (!branch)
										{
	                    $('#tweetcontainer').html(response.responseText);
	                    jQuery("#loadingtweets").hide();
										}
									else { $('#'+branch).html(response.responseText); }
	              },
	            error: function ()
								{
									if (!branch)
										{
											$('#tweetcontainer').html('error!');
										}
									else
									 {
										 $('#'+branch).html('error!');
									 }
								}
	           });
	  }

		function go_to_hashtag(hashtag)
			{
				document.getElementById("specific_types").checked = true;
				var sel=document.getElementById('any_hashtags');
				$(".slidingDiv2").show();
				$("#specify_types").show();
				sel.value=hashtag;
				visualize();
		 }

		 function go_to_user(user)
 			{
				document.getElementById("specific_types").checked = true;
				var sel=document.getElementById('from_accounts');
				$(".slidingDiv2").show();
				$("#specify_types").show();
 				sel.value=user;
 				visualize();
 		 }


	function toggle_tweets(id,name)
		{
		  var e = document.getElementsByClassName(id+'_'+name);
		  for(i in e)
			{
		     if(!e[i]) return true;
		     if(e[i].style.display=="none"){e[i].style.display="block";}
				 else {e[i].style.display="none";}
		  }
		  return true;
	 }

	 	function continueExecution()
	 	  {
	 		 alert(sel.options[sel.selectedIndex].value);
	 	  }

	  function visualize(refresh="")
	 		  {
	 				var qry=[];
					var sel = document.getElementById('case');
	 			  qry['table'] = sel.options[sel.selectedIndex].value;
	 				if (!qry['table']) { alert('Please select at least one case to visualise!'); return; }
					var temp_bool=document.getElementById("bool_op");
					qry['bool_op']=temp_bool.options[temp_bool.selectedIndex].value;
	 				var e = document.getElementById("time_unit");

	 				try
	 				{
	 						qry['drill_level'] = e.options[e.selectedIndex].value;
	 						qry['startdate']=document.getElementById("startdate").value;
	 						qry['starttime']=document.getElementById("starttime").value;
	 						qry['enddate']=document.getElementById("enddate").value;
	 						qry['endtime']=document.getElementById("endtime").value;
	 						var errormsg="";
	 						errormsg=checkDate(document.getElementById("startdate"));
	 						if(errormsg) { alert('Start date error { '+errormsg+' }'); return; }
	 						errormsg=checkTime(document.getElementById("starttime"));
	 						if(errormsg) { alert('Start time error { ' +errormsg+' }'); return; }
	 						errormsg=checkDate(document.getElementById("enddate"));
	 						if(errormsg) { alert('End date error { '+errormsg+' }'); return; }
	 						errormsg=checkTime(document.getElementById("endtime"));
	 						if(errormsg) { alert('End time error { '+errormsg+' }'); return; }
							qry[getRadioValue("graph_metrics")]=1;
	 						qry['languages']=getRadioValue("languages");
	 						qry['sources']=getRadioValue("sources");
	 						qry['types']=getRadioValue("content_types");
	 						if (getRadioValue("period1")=="all") { qry['startdate']=""; qry['enddate']=""; }
	 						qry['retweets']=getBoxValue("include_retweets");
	 						qry['image_tweets']=getBoxValue("image_tweets");
	 						qry['video_tweets']=getBoxValue("video_tweets");
	 						qry['link_tweets']=getBoxValue("link_tweets");
	 						qry['retweet_tweets']=getBoxValue("retweet_tweets");
	 						qry['response_tweets']=getBoxValue("response_tweets");
              qry['quoting_tweets']=getBoxValue("quoting_tweets");
              qry['referenced_tweets']=getBoxValue("referenced_tweets");
              qry['mentions_tweets']=getBoxValue("mentions_tweets");
	 						qry['responded_tweets']=getBoxValue("responded_tweets");
							var ht=document.getElementById("any_hashtags").value.toLowerCase();
							ht = ht.replace(/#/,' ');
							qry['any_hashtags']=ht;
	 						qry['any_keywords']=document.getElementById("any_keywords").value.toLowerCase();
	 						qry['all_keywords']=document.getElementById("all_keywords").value.toLowerCase();
	 						qry['exact_phrase']=document.getElementById("exact_phrase").value.toLowerCase();
	 						if (!qry['from_accounts']) qry['from_accounts']=document.getElementById("from_accounts").value.toLowerCase();
							qry['in_reply_to_tweet_id']=document.getElementById("in_reply_to_tweet_id").value;
	 						qry['location']=(document.getElementById("location").value).toLowerCase();
	 						qry['min_retweets']=document.getElementById("min_retweets").value;
	 						qry['hashtag_cloud']=getBoxValue("hashtag_cloud");
	 						qry['user_verified']=getBoxValue("user_verified");
	 						if ($('#last_graph_hash').length != 0) {qry['last_graph_hash']=document.getElementById("last_graph_hash").value;}
	 						qry['stackgraph']=getBoxValue("stackgraph");

	 						if (qry['types']=="all" || (qry['image_tweets'] && qry['video_tweets'] && qry['link_tweets'] && qry['retweet_tweets']
	 							&& qry['response_tweets'] && qry['responded_tweets'] && qry['quoting_tweets'] && qry['referenced_tweets'] && qry['any_hashtags']=='' && qry['any_keywords']=='' && qry['all_keywords']==''
	 							&& qry['exact_phrase']=='' && qry['from_accounts']=='' && qry['in_reply_to_tweet_id']=='' && qry['location']=='' && qry['min_retweets']=='' && qry['user_verified']))
								{ qry['types']=""; }
	 						else qry['types']="some";

	 						if (qry['languages']=="all") { qry['languages']=""; }
	 						else if (qry['languages']=="en") { qry['languages']="en"; }
	 						else if (qry['languages']=="some") { qry['languages']=document.getElementById("language").value; }
	 						else { qry['languages']=""; }
	 						if (qry['sources']=="all") { qry['sources']=""; }
	 						else if (qry['sources']=="some")
	 							{
	 								var x = document.getElementsByName("other_source");
	 								var i;
	 								qry['sources']="";
	 								for (i = 0; i < x.length; i++)
	 									{
	 			    					if (x[i].checked)
	 											{
	 												if (!qry['sources']) { qry['sources']=x[i].value; }
	 												else { qry['sources'] = qry['sources']+','+x[i].value; }
	 											}
	 									}
	 									if (document.getElementById("other_source").value)
	 									{
	 										if (!qry['sources']) { qry['sources']=document.getElementById("other_source").value; }
	 										else { qry['sources']=qry['sources']+','+document.getElementById("other_source").value;}
	 									}
	 							}
	 						else if (qry['sources']!='only_mobile' && qry['sources']!='only_web') { qry['sources']=""; }
	 				 } catch (e) { alert("error:"+e.message); }

	 				var url='fetch_tweets.php?demo=1';
	 				for(var key in qry)
	 					{
	 							if(qry.hasOwnProperty(key))
	 								{
	 										if (qry[key]) { url=url+'&'+key+'='+encodeURIComponent(qry[key]); }
	 								}
	 					}
	 				$('#chartcontainer').html('');
	 				$("#loadingchart").show();
	 				$.ajax({url:url,
	 								complete: function (response)
	 									{
	 										$('#chartcontainer').html(response.responseText);
	 										try { update_series_urls(); }
	 										catch(err) { $("#loadingchart").hide(); return false; }
	 										$("#loadingchart").hide();
	 										if (($('input[name="toptweets"]:checked').val()))
	 										{
	 											$('#tweetcontainer').html('');
	 											$("#loadingtweets").show();
	 											$.ajax({url:url+'&point=1&asc=1&order_d=1&refresh='+refresh,
	 															complete: function (response)
	 																{
	 																		$('#tweetcontainer').html(response.responseText);
	 																		jQuery("#loadingtweets").hide();
	 																		$("#loadingchart").hide();
	 																},
	 															error: function () { $('#tweetcontainer').html('error!'); 	$("#loadingchart").hide(); $("#loadingchart").hide(); }
	 														 });
	 										}
	 									},
	 								error: function () { $('#chartcontainer').html('error!'); $("#tweetcontainer").hide(); $("#loadingchart").hide(); }
	 							 });
	 				return true;
	 		  }

function ValidateDateTime(field)
		{
			 var dtValue=document.getElementById(field).value.trim();
			 var re = /^(\d{4}-\d{2}-\d{2})$/;
			 if (dtValue.match(re))
			 	{
					dtValue=dtValue+" 00:00:00";
					document.getElementById(field).value=dtValue;
					if (field=="case_from") { document.login.case_from.value=dtValue; }
					if (field=="case_to") { document.login.case_to.value=dtValue; }
				}
			 else {  }
		   var dtRegex = new RegExp(/^\d{4}-(0[1-9]|1[0-2])-([0-2]\d|3[01]) ([0-2][0-9]|):[0-5]\d:[0-5]\d$/);
			 if (!dtRegex.test(dtValue) && dtValue.length>0)
			 	{
					$('.'+field).html("<br><b>The datetime value ("+dtValue+") is invalid.</b>");
				}
			else { $('.'+field).html(""); }

		}
