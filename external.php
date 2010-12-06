<?php
//require the php OAuth library
require_once "lib/oauth.php";

class MyOAuthSignatureMethod_RSA_SHA1 extends OAuthSignatureMethod_RSA_SHA1 {
	protected function fetch_public_cert(&$request) {
	    $s = curl_init();
		curl_setopt($s,CURLOPT_URL,$_GET['xoauth_signature_publickey']);
		curl_setopt($s, CURLOPT_RETURNTRANSFER, 1);
		$cert = curl_exec($s);
		curl_close($s);
		return $cert;
	}
	protected function fetch_private_cert(&$request) {
		return;
	}
}

$request = OAuthRequest::from_request();
$server = new MyOAuthSignatureMethod_RSA_SHA1();


$return = $server->check_signature($request, null, null, $_GET['oauth_signature']);

if (! $return) {
	die('invalid signature');
}

$data = json_decode(file_get_contents('php://input'), true);

?>
<script xmlns:os="http://ns.opensocial.org/2008/markup" type="text/os-data">
    <os:PeopleRequest key="Viewer" userId="@viewer" groupId="@self"/>
    <os:PersonAppDataRequest key="twitterName" userId="@viewer" fields="twitterName" />
    <os:PeopleRequest key="ViewerFriends" userId="@viewer" groupId="@friends"/>
    <os:PersonAppDataRequest key="twitterNameFriends" userId="@viewer" groupId="@friends" fields="twitterName" />

</script>

Hello <?= $data[0]['result']['displayName'] ?> from Proxied Content.

<a href="javascript:postToFeed()">post</a>

<script type="text/os-template" xmlns:os="http://ns.opensocial.org/2008/markup" require="ViewerFriends">
    <ul>
        <li repeat="${ViewerFriends}">
            <a href="javascript:;" id="${Cur.id}" class="friendLink">${Cur.displayName}</a>
        </li>
    </ul>
</script>

<script type="text/os-template" xmlns:os="http://ns.opensocial.org/2008/markup" require="Viewer">
    Hello ${Viewer.displayName}
    <div if="${Viewer.isOwner}">
        <p>Enter your Twitter Name:</p>
        <input type="text" id="twitterName" value="${twitterName}" />
        <input type="submit" id="submitTwitterName" value="Save" />
    </div>
</script>

<script type="text/os-template" xmlns:abc="http://example.com/myapp" xmlns:os="http://ns.opensocial.org/2008/markup" require="feed" autoUpdate="true">
    <abc:feedList entries="${feed}" />
</script>

<script type="text/javascript">
    function loadFeed(twitterName) {
        var params = {};
        params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;

        gadgets.io.makeRequest(
        'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=' + twitterName,
        function(response) {
            opensocial.data.DataContext.putDataSet('feed', response.data);
        },
        params);
    }

    function postToFeed() {
        var params = {};
        params[gadgets.io.RequestParameters.AUTHORIZATION]=gadgets.io.AuthorizationType.OAUTH;
        params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME]='MyTwitter';
        params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.POST;
        params[gadgets.io.RequestParameters.POST_DATA] = gadgets.io.encodeValues({
          status : "Hello World from the OpenSocial Europe Summit"
        });
        gadgets.io.makeRequest('http://api.twitter.com/1/statuses/update.json', function(response) {
           console.log(response);
           console.log(response.data);
           if (response.oauthApprovalUrl) {
              window.open(response.oauthApprovalUrl);
            }
        }, params );
    }

    function postToWall(id) {
        vz.embed.getEmbedUrl({id: id}, function(embedUrl) {
            var params = [];
            params[opensocial.Message.Field.TYPE] = opensocial.Message.Type.PUBLIC_MESSAGE;
            var message = opensocial.newMessage('Have a look at this tweet ' + embedUrl, params);
            var recipient = "VIEWER";
            opensocial.requestSendMessage(recipient, message, function() {});
        });
    }

    gadgets.util.registerOnLoadHandler(function() {
        var viewer = opensocial.data.DataContext.getDataSet('Viewer');
        var twitterName = opensocial.data.DataContext.getDataSet('twitterName');
        var twitterNameFriends = opensocial.data.DataContext.getDataSet('twitterNameFriends');

        if (twitterName != false) {
            twitterName = twitterName[viewer.id].twitterName;
            loadFeed(twitterName);
        }

        $('#submitTwitterName').click(function() {
            osapi.appdata.update(
                {userId: '@viewer',
                data: {twitterName: $('#twitterName').val()}
            }).execute(function() {
                loadFeed($('#twitterName').val());
            });
        });

        $('.friendLink').click(function() {
            var friendId = $(this).attr('id');
            if (twitterNameFriends[friendId]) {
                loadFeed(twitterNameFriends[friendId].twitterName);
            }
        });
    });
</script>