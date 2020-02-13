
var $answerSelect = $('.answer-select');
var $answerText = $('.answer-text');
var $message = $('#message');
var $newMessage = $('.new-message');

$message.on('focus', function(ev) {
  $newMessage.addClass('active');  
});

$message.on('click', function(ev) {
  $message.focus();
});

$answerSelect.on('change', 'select', function(ev) {
  updateAnswerMessage( $(ev.currentTarget).val() );
});

function updateAnswerMessage( answerId )
{
  var answerText = $answerText
      .filter('[data-answer-id=' + answerId + ']')
      .first().html().trim();

  var messageText = $message.val().trim();
  var confirmed  = true;

  if (answerText && answerText.length)
  {
    if (messageText && messageText.length) {
      confirmed = window.confirm('Do you really want to replace your answer with the selected one?');
    }

    if (confirmed) {
      $message.val(answerText).focus();
    }
  }

}
